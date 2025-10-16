<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\MethodPathDefinition;
use Apilyser\Extractor\ClassUsage;
use Apilyser\Extractor\MethodPathExtractor;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Resolver\VariableAssignmentFinder;
use Apilyser\Traverser\ClassUsageTraverser;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Property;

class MethodAnalyser
{
    
    private $variableAssignmentFinder;

    public function __construct(
        private MethodPathExtractor $methodPathExtractor,
        private ResponseResolver $responseResolver,
        private HttpDelegate $httpDelegate,
        private ClassUsageTraverserFactory $classUsageTraverserFactory,
        private ClassAstResolver $classAstResolver,
        private TypeStructureResolver $typeStructureResolver
    ) {
        $this->variableAssignmentFinder = new VariableAssignmentFinder();
    }

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseCall[]
     */
    public function analyse(ClassMethodContext $context): array
    {
        return $this->analyseMethod($context);
    }

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseCall[]
     */
    private function analyseMethod(ClassMethodContext $context): array 
    {   
        $paths = $this->methodPathExtractor->extract($context->method);

        $results = [];
        foreach ($paths as $path) {
            $pathResults = $this->analysePath($path, $context);
            array_push($results, ...$pathResults);
        }
        
        return $results;
    }

    /**
     * Analyze a single execution path
     * Returns all possible ResponseCall objects from this path
     * 
     * @return ResponseCall[]
     */
    private function analysePath(MethodPathDefinition $path, ClassMethodContext $context): array 
    {
        $results = [];
        $statementNodes = array_map(
            fn($statement) => $statement->getNode(),
            $path->getStatements()
        );
        
        // Find response class usages.
        $usedResponseClasses = $this->findUsedResponseClassesInPath($path, $context);
        $classResults = $this->responseResolver->resolve($context, $statementNodes, $usedResponseClasses);
        array_push($results, ...$classResults);

        // Find all method calls in entire path
        foreach ($statementNodes as $node) {
            $methodCalls = $this->findMethodCalls($node);

            foreach ($methodCalls as $methodCall) {
                $childResults = $this->analyseMethodCall($methodCall, $context, $statementNodes);
                array_push($results, ...$childResults);
            }
        }
        
        return $results;
    }

    /**
     * @return MethodCall[]
     */
    private function findMethodCalls(Node $node): array 
    {
        $methodCalls = [];
        
        // If this node itself is a method call, add it
        if ($node instanceof MethodCall) {
            $methodCalls[] = $node;
        }
        
        // Recursively search all child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;
            
            if ($subNode instanceof Node) {
                $childCalls = $this->findMethodCalls($subNode);
                array_push($methodCalls, ...$childCalls);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $childCalls = $this->findMethodCalls($item);
                        array_push($methodCalls, ...$childCalls);
                    }
                }
            }
        }
        
        return $methodCalls;
    }

    /**
     * @return ResponseCall[]
     */
    private function analyseMethodCall(
        MethodCall $methodCall, 
        ClassMethodContext $context, 
        array $statementNodes
    ): array {
        $var = $methodCall->var;
        
        // If method in same class
        if ($var instanceof Variable && $var->name === 'this') {
            return $this->analyseThisMethodCall($methodCall, $context);
        }
        
        // If method from different class that exist in current class (ex $this->service->someMethod())
        if ($var instanceof PropertyFetch && $var->var instanceof Variable && $var->var->name === 'this') {
            return $this->analysePropertyMethodCall($methodCall, $context);
        }
        
        // From object variable
        if ($var instanceof Variable) {
            return $this->analyseVariableMethodCall($methodCall, $context, $statementNodes);
        }
        
        return [];
    }

    private function analyseThisMethodCall(MethodCall $methodCall, ClassMethodContext $context): array 
    {
        $methodName = $methodCall->name->name;
        
        // Find the method in the class
        $calledMethod = $this->findMethodInClass($context->class, $methodName);
        if (!$calledMethod) {
            return [];
        }
        
        $childContext = new ClassMethodContext(
            class: $context->class,
            method: $calledMethod,
            imports: $context->imports
        );
        
        // Recursively analyze - this will return ALL possible responses from that method
        return $this->analyseMethod($childContext);
    }

    private function analysePropertyMethodCall(MethodCall $methodCall, ClassMethodContext $context): array 
    {
        $methodName = $methodCall->name->name;
        
        // Find the property in the class
        $propertyFetch = $methodCall->var;
        if (!$propertyFetch instanceof PropertyFetch) {
            return [];
        }
        $property = $this->classAstResolver->findPropertyInClass($context->class, $propertyFetch);
        if (!$property) {
            return [];
        }
        
        $propertyClassName = $this->getPropertyClassName($property);
        if (!$propertyClassName) {
            return [];
        }
        
        $classStructure = $this->classAstResolver->resolveClassStructure($propertyClassName, $context->imports);
        if (!$classStructure) {
            return [];
        }
        
        $calledMethod = $this->findMethodInClass($classStructure->class, $methodName);
        if (!$calledMethod) {
            return [];
        }
        
        $childContext = new ClassMethodContext(
            class: $classStructure->class,
            method: $calledMethod,
            imports: $context->imports
        );
        
        return $this->analyseMethod($childContext);
    }

    private function analyseVariableMethodCall(
        MethodCall $methodCall, 
        ClassMethodContext $context,
        array $statementNodes
    ): array {
        if (!$methodCall->var instanceof Variable) {
            return [];
        }

        $variableName = $methodCall->var->name;
        $methodName = $methodCall->name->name;
        
        $assignment = $this->variableAssignmentFinder->findAssignment($variableName, $statementNodes);
        if (!$assignment || !($assignment instanceof New_)) {
            return [];
        }
        
        // Get the class name from "new ClassName()"
        $className = $assignment->class->name ?? null;
        if (!is_string($className)) {
            return [];
        }
        
        $classStructure = $this->classAstResolver->resolveClassStructure($className, $context->imports);
        if (!$classStructure) {
            return [];
        }
        
        $calledMethod = $this->findMethodInClass($classStructure->class, $methodName);
        if (!$calledMethod) {
            return [];
        }
        
        $childContext = new ClassMethodContext(
            class: $classStructure->class,
            method: $calledMethod,
            imports: $context->imports
        );
        
        return $this->analyseMethod($childContext);
    }

    private function getPropertyClassName(Property $property): ?string 
    {
        $type = $property->type;
        
        if ($type instanceof NullableType) {
            $type = $type->type;
        }
        
        if ($type instanceof Name) {
            return $type->toString();
        }
        
        if ($type instanceof Identifier) {
            // Built-in types like 'string', 'int', 'array' - not classes
            return null;
        }
        
        return null;
    }

    /**
     * Find the method in a class by name
     */
    private function findMethodInClass(\PhpParser\Node\Stmt\Class_ $class, string $methodName): ?\PhpParser\Node\Stmt\ClassMethod 
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod && $stmt->name->name === $methodName) {
                return $stmt;
            }
        }
        return null;
    }

    /**
     * @return ClassUsage[]
     */
    private function findUsedResponseClassesInPath(MethodPathDefinition $path, ClassMethodContext $context): array
    {
        $usedResponseClasses = [];

        foreach ($this->httpDelegate->getParsers() as $httpParser) {
            $usedClass = $this->processClassInPath($path, $httpParser, $context->imports);
            array_push(
                $usedResponseClasses,
                ...$usedClass
            );
        }

        return $usedResponseClasses;
    }

    /**
     * @return ClassUsage[]
     */
    private function processClassInPath(MethodPathDefinition $path, ApiParser $httpParser, array $imports): array
    {
        $usedClasses = $httpParser->getSupportedResponseClasses();

        /** @var ClassUsage[] */
        $usages = [];

        foreach ($path->getStatements() as $stmts) {
            $node = $stmts->getNode();

            foreach ($usedClasses as $usedClass) {
                $traverser = $this->classUsageTraverserFactory->create(
                    className: $usedClass,
                    imports: $imports
                );

                $this->processNode($node, $traverser);
                array_push($usages, ...$traverser->getUsages());
            }
        }

        return $usages;
    }

    private function processNode(Node $node, ClassUsageTraverser $traverser)
    {   
        $traverser->enterNode($node);

        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;

            if ($subNode instanceof Node) {
                $this->processNode($subNode, $traverser);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->processNode($item, $traverser);
                    }
                }
            }
        }
    }
}