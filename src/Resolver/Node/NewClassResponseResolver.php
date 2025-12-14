<?php declare(strict_types=1);

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Definition\NewClassResponseParameter;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Resolver\VariableAssignmentFinder;
use Exception;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Property;

class NewClassResponseResolver implements ResponseNodeResolver
{

    public function __construct(
        private NamespaceResolver $namespaceResolver,
        private TypeStructureResolver $typeStructureResolver,
        private HttpDelegate $httpDelegate,
        private VariableAssignmentFinder $variableAssignmentFinder,
        private ClassAstResolver $classAstResolver
    ) {}

    public function canResolve(Node $node): bool
    {
        return $node instanceof New_;
    }

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Node $node
     * @param ?ResponseCall $modifierResponseCall
     * 
     * @return ?ResponseCall
     */
    public function resolve(
        ClassMethodContext $context, 
        array $methodJourney,
        Node $node, 
        ?ResponseCall $modifierResponseCall = null
    ): ?ResponseCall {
        if (!$node instanceof New_ || !$node->class instanceof Name) {
            throw new Exception("Invalid node type");
        }

        $className = $node->class->name;
        $fullClassName = $this->namespaceResolver->findFullNamespaceForClass(
            className: $className, 
            imports: $context->imports,
            currentNamespace: $context->namespace
        );

        $responseParser = $this->getResponseParser($fullClassName);
        if (!$responseParser) {
            throw new Exception("Response parser not found");
        }

        $parameters = $responseParser->getNewClassParameters();
        return $this->getResponse(
            context: $context,
            methodJourney: $methodJourney,
            class: $node,
            parameterInfo: $parameters
        );
    }

    private function getResponseParser(string $fullClassName): ?ApiParser
    {
        // Find the http parser for this response class.
        $httpParsers = $this->httpDelegate->getParsers();
        foreach ($httpParsers as $httpParser) {
            if ($httpParser->supportResponseClass($fullClassName)) {
                return $httpParser;
            }
        }

        return null;
    }

    function getResponse(
        ClassMethodContext $context,
        array $methodJourney,
        New_ $class, 
        NewClassResponseParameter $parameterInfo
    ): ?ResponseCall
    {
        $statusCode = null;
        $body = null;
        
        // Track if status code was explicitly provided
        $statusCodeProvided = false;

        foreach ($class->args as $index => $arg)
        {
            if ($arg->name != null) {
                // Named parameters
                if ($arg->name->name == $parameterInfo->bodyName) {
                    $body = $this->typeStructureResolver->resolveFromExpression(
                        context: $context,
                        methodJourney: $methodJourney,
                        expr: $arg->value
                    );
                } else if ($arg->name->name == $parameterInfo->statusCodeName) {
                    $statusCodes = $this->findStatusCodes($arg->value, $context, $methodJourney);
                    $statusCode = !empty($statusCodes) ? $statusCodes[0] : null;
                    $statusCodeProvided = true;
                }
            } else {
                // Positional parameters
                if ($index == $parameterInfo->bodyIndex) {
                    // Body
                    $body = $this->typeStructureResolver->resolveFromExpression(
                        context: $context,
                        methodJourney: $methodJourney,
                        expr: $arg->value
                    );
                } else if ($index == $parameterInfo->statusCodeIndex) {
                    // Status code
                    $statusCodes = $this->findStatusCodes($arg->value, $context, $methodJourney);
                    $statusCode = !empty($statusCodes) ? $statusCodes[0] : null;
                    $statusCodeProvided = true;
                }
            }
        }

        // If status code was not provided, get default from constructor
        if (!$statusCodeProvided && $parameterInfo->defaultStatusCode !== null) {
            $statusCode = $parameterInfo->defaultStatusCode;
        }

        return new ResponseCall(
            type: 'application/json',
            structure: $body,
            statusCode: $statusCode
        );
    }

    /**
     * Finds all possible status codes from a node
     * 
     * @param Node $node
     * @param ClassMethodContext $context
     * @param array $methodJourney
     * @return int[] Array of possible status codes
     */
    private function findStatusCodes(Node $node, ClassMethodContext $context, array $methodJourney): array
    {
        return match (true) {
            $node instanceof Int_ => [$node->value],
            $node instanceof Variable => $this->handleVariable($node, $context, $methodJourney),
            $node instanceof PropertyFetch => $this->handlePropertyFetch($node, $context, $methodJourney),
            $node instanceof ClassConstFetch => $this->handleClassConstFetch($node, $context),
            $node instanceof MethodCall => $this->handleMethodCall($node, $context, $methodJourney),
            default => null
        };
    }

    private function handleVariable(Variable $variable, ClassMethodContext $context, array $methodJourney): array
    {
        $variableName = $variable->name;
        $assignedExpr = $this->variableAssignmentFinder->findAssignment($variableName, $methodJourney);

        if (null !== $assignedExpr) {
            return $this->findStatusCodes($assignedExpr, $context, $methodJourney);
        }

        return [];
    }

    private function handlePropertyFetch(PropertyFetch $propertyFetch, ClassMethodContext $context, array $methodJourney): array
    {
        $property = $this->classAstResolver->findPropertyInClass($context->class, $propertyFetch);

        if (null !== $property) {
            $statusCode = $this->getBodyFromProperty($property);
            return $statusCode !== null ? [$statusCode] : [];
        }

        return [];
    }

    private function getBodyFromProperty(Property $property): ?int
    {
        $type = $property->props[0];
        if ($type instanceof PropertyItem && $type->default instanceof Int_) {
            return $type->default->value;
        }

        return null;
    }

    private function handleClassConstFetch(ClassConstFetch $classConstFetch, ClassMethodContext $context): array
    {
        $constName = $classConstFetch->name;
        if ($constName instanceof Identifier) {
            $classStructure = $this->classAstResolver->resolveClassStructure(
                $context->namespace, 
                $classConstFetch->class->name, 
                $context->imports
            );
            
            $constant = $this->classAstResolver->findConstInClass($classStructure->class, $constName->name);

            if (null !== $constant) {
                if ($constant->value instanceof Int_) {
                    return [$constant->value->value];
                }
            }
        }

        return [];
    }

    private function handleMethodCall(MethodCall $methodCall, ClassMethodContext $context, array $methodJourney): array
    {
        // Check if it's a method call on $this
        if ($methodCall->var instanceof Variable && $methodCall->var->name === 'this') {
            return $this->handleThisMethodCall($methodCall, $context, $methodJourney);
        }
        
        // Check if it's a method call on another object (e.g., $service->getStatusCode())
        if ($methodCall->var instanceof Variable) {
            return $this->handleExternalMethodCall($methodCall, $context, $methodJourney);
        }
        
        return [];
    }

    private function handleThisMethodCall(MethodCall $methodCall, ClassMethodContext $context, array $methodJourney): array
    {
        if (!$methodCall->name instanceof Identifier) {
            return [];
        }
        
        $methodName = $methodCall->name->name;
        
        // Find the method in the current class
        $method = $this->classAstResolver->findMethodInClass($context->class, $methodName);
        
        if ($method === null) {
            return [];
        }
        
        // Find ALL return statements in the method
        $returnStatements = $this->findAllReturnValues($method->stmts);
        
        if (empty($returnStatements)) {
            return [];
        }
        
        // Collect all possible status codes
        $statusCodes = [];
        foreach ($returnStatements as $returnExpr) {
            $foundCodes = $this->findStatusCodes($returnExpr, $context, $methodJourney);
            $statusCodes = array_merge($statusCodes, $foundCodes);
        }
        
        // Remove duplicates and return
        return array_values(array_unique($statusCodes));
    }

    private function handleExternalMethodCall(MethodCall $methodCall, ClassMethodContext $context, array $methodJourney): array
    {
        if (!$methodCall->var instanceof Variable || !$methodCall->name instanceof Identifier) {
            return [];
        }
        
        $variableName = $methodCall->var->name;
        $methodName = $methodCall->name->name;
        
        // Find where this variable is defined
        $variableType = $this->findVariableType($variableName, $context, $methodJourney);
        
        if ($variableType === null) {
            return [];
        }
        
        // Resolve the class structure for the variable's type
        $classStructure = $this->classAstResolver->resolveClassStructure(
            $context->namespace,
            $variableType,
            $context->imports
        );
        
        if ($classStructure === null) {
            return [];
        }
        
        // Find the method in that class
        $method = $this->classAstResolver->findMethodInClass($classStructure->class, $methodName);
        
        if ($method === null) {
            return [];
        }
        
        // Create a new context for the external class
        $externalContext = new ClassMethodContext(
            namespace: $classStructure->namespace,
            imports: $classStructure->imports,
            class: $classStructure->class,
            method: $method
        );
        
        // Find ALL return statements in the external method
        $returnStatements = $this->findAllReturnValues($method->stmts);
        
        if (empty($returnStatements)) {
            return [];
        }
        
        // Collect all possible status codes
        $statusCodes = [];
        foreach ($returnStatements as $returnExpr) {
            $foundCodes = $this->findStatusCodes($returnExpr, $externalContext, []);
            $statusCodes = array_merge($statusCodes, $foundCodes);
        }
        
        // Remove duplicates and return
        return array_values(array_unique($statusCodes));
    }

    /**
     * Recursively finds ALL return statement values in a method
     * 
     * @param Node[] $stmts
     * @return Node[]
     */
    private function findAllReturnValues(array $stmts): array
    {
        $returnValues = [];
        
        foreach ($stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Return_ && $stmt->expr !== null) {
                $returnValues[] = $stmt->expr;
            }
            
            // Recursively search in nested structures
            if (property_exists($stmt, 'stmts') && is_array($stmt->stmts)) {
                $nestedReturns = $this->findAllReturnValues($stmt->stmts);
                $returnValues = array_merge($returnValues, $nestedReturns);
            }
            
            // Handle if-else statements
            if ($stmt instanceof \PhpParser\Node\Stmt\If_) {
                foreach ($stmt->elseifs as $elseif) {
                    $elseifReturns = $this->findAllReturnValues($elseif->stmts);
                    $returnValues = array_merge($returnValues, $elseifReturns);
                }
                
                if ($stmt->else !== null) {
                    $elseReturns = $this->findAllReturnValues($stmt->else->stmts);
                    $returnValues = array_merge($returnValues, $elseReturns);
                }
            }
            
            // Handle try-catch statements
            if ($stmt instanceof \PhpParser\Node\Stmt\TryCatch) {
                foreach ($stmt->catches as $catch) {
                    $catchReturns = $this->findAllReturnValues($catch->stmts);
                    $returnValues = array_merge($returnValues, $catchReturns);
                }
                
                if ($stmt->finally !== null) {
                    $finallyReturns = $this->findAllReturnValues($stmt->finally->stmts);
                    $returnValues = array_merge($returnValues, $finallyReturns);
                }
            }
        }
        
        return $returnValues;
    }

    /**
     * Finds the type of a variable (class name)
     * 
     * @param string $variableName
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @return string|null Class name
     */
    private function findVariableType(string $variableName, ClassMethodContext $context, array $methodJourney): ?string
    {
        // 1. Check method parameters
        foreach ($context->method->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $variableName) {
                if ($param->type instanceof Name) {
                    return $param->type->name;
                }
            }
        }
        
        // 2. Check constructor injection
        $constructorParam = $this->classAstResolver->findConstructorParam($context->class, $variableName);
        if ($constructorParam !== null && $constructorParam->type instanceof Name) {
            return $constructorParam->type->name;
        }
        
        return null;
    }

}