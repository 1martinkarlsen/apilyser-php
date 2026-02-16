<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\MethodPathDefinition;
use Apilyser\Ast\ClassUsage;
use Apilyser\Ast\ExecutionPathFinder;
use Apilyser\Ast\Node\NameHelper;
use Apilyser\Ast\VariableAssignmentFinder;
use Apilyser\Framework\FrameworkAdapter;
use Apilyser\Framework\FrameworkRegistry;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Ast\Visitor\ClassUsageVisitor;
use Apilyser\Ast\Visitor\ClassUsageVisitorFactory;
use Apilyser\Util\Logger;
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
        private Logger $logger,
        private ExecutionPathFinder $executionPathFinder,
        private ResponseResolver $responseResolver,
        private FrameworkRegistry $frameworkRegistry,
        private ClassUsageVisitorFactory $classUsageVisitorFactory,
        private ClassAstResolver $classAstResolver
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
        $paths = $this->executionPathFinder->extract($context->method);

        $this->logger->info("Found " . count($paths) . " execution paths");

        $results = [];
        foreach ($paths as $index => $path) {
            $this->logger->info("Path number " . $index);
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

        $this->logger->info("Used response classes: " . count($usedResponseClasses));
        foreach ($usedResponseClasses as $usedClass) {
            $this->logger->info(" - " . $usedClass->className . " - " . $usedClass->usageType);
        }

        $classResults = $this->responseResolver->resolve($context, $statementNodes, $usedResponseClasses);
        array_push($results, ...$classResults);

        $this->logger->info("Class results " . count($classResults));

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

        if (!$this->isResponseReturningMethod($calledMethod, $context->imports)) {
            return [];
        }

        $childContext = new ClassMethodContext(
            namespace: $context->namespace,
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
        $propertyName = $methodCall->var->name->name ?? '?';
        $this->logger->info("[PropertyCall] \$this->{$propertyName}->{$methodName}()");

        // Find the property in the class
        $propertyFetch = $methodCall->var;
        if (!$propertyFetch instanceof PropertyFetch) {
            $this->logger->info("[PropertyCall] STOP: not a PropertyFetch");
            return [];
        }

        $property = $this->classAstResolver->findPropertyInClass($context->class, $propertyFetch);

        $propertyClassName = null;
        if ($property) {
            $propertyClassName = $this->getPropertyClassName($property);
            $this->logger->info("[PropertyCall] Found traditional property, type: " . ($propertyClassName ?? 'null'));
        } else {
            // Fallback: check constructor promoted properties
            $constructorParam = $this->classAstResolver->findConstructorParam(
                $context->class,
                $propertyFetch->name->name
            );
            if ($constructorParam?->type instanceof Name) {
                $propertyClassName = NameHelper::getName($constructorParam->type);
                $this->logger->info("[PropertyCall] Found promoted property, type: " . $propertyClassName);
            } else if ($constructorParam?->type instanceof Identifier) {
                $propertyClassName = $constructorParam->type->name;
                $this->logger->info("[PropertyCall] Found promoted property (identifier), type: " . $propertyClassName);
            } else {
                $this->logger->info("[PropertyCall] STOP: no property or constructor param found for '{$propertyName}'");
            }
        }

        if (!$propertyClassName) {
            $this->logger->info("[PropertyCall] STOP: could not resolve property class name");
            return [];
        }

        $classStructure = $this->classAstResolver->resolveClassStructure($context->namespace, $propertyClassName, $context->imports);
        if (!$classStructure) {
            $this->logger->info("[PropertyCall] STOP: could not resolve class structure for '{$propertyClassName}'");
            return [];
        }
        $this->logger->info("[PropertyCall] Resolved class: " . $classStructure->class->name->name);

        $calledMethod = $this->findMethodInClass($classStructure->class, $methodName);
        if (!$calledMethod) {
            $this->logger->info("[PropertyCall] STOP: method '{$methodName}' not found in " . $classStructure->class->name->name);
            return [];
        }
        $this->logger->info("[PropertyCall] Found method: " . $classStructure->class->name->name . "::{$methodName}()");

        $returnType = $calledMethod->returnType;
        $returnTypeStr = $returnType ? ($returnType instanceof Name ? $returnType->toString() : ($returnType instanceof Identifier ? $returnType->name : get_class($returnType))) : 'none';
        $this->logger->info("[PropertyCall] Return type: " . $returnTypeStr);

        if (!$this->isResponseReturningMethod($calledMethod, $classStructure->imports)) {
            $this->logger->info("[PropertyCall] STOP: return type does not match a response class");
            return [];
        }

        $this->logger->info("[PropertyCall] MATCH - recursing into " . $classStructure->class->name->name . "::{$methodName}()");

        $childContext = new ClassMethodContext(
            namespace: $classStructure->namespace,
            class: $classStructure->class,
            method: $calledMethod,
            imports: $classStructure->imports
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

        $classStructure = $this->classAstResolver->resolveClassStructure($context->namespace, $className, $context->imports);
        if (!$classStructure) {
            return [];
        }

        $calledMethod = $this->findMethodInClass($classStructure->class, $methodName);
        if (!$calledMethod) {
            return [];
        }

        if (!$this->isResponseReturningMethod($calledMethod, $classStructure->imports)) {
            return [];
        }

        $childContext = new ClassMethodContext(
            namespace: $classStructure->namespace,
            class: $classStructure->class,
            method: $calledMethod,
            imports: $classStructure->imports
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
     * Checks if a method's return type is a supported response class.
     *
     * @param \PhpParser\Node\Stmt\ClassMethod $method
     * @param array $imports Imports from the file where the method is defined
     *
     * @return bool
     */
    private function isResponseReturningMethod(\PhpParser\Node\Stmt\ClassMethod $method, array $imports): bool
    {
        $returnType = $method->returnType;

        if ($returnType === null) {
            return false;
        }

        if ($returnType instanceof NullableType) {
            $returnType = $returnType->type;
        }

        if ($returnType instanceof Identifier) {
            return false;
        }

        if ($returnType instanceof Name) {
            $shortName = $returnType->toString();

            foreach ($this->frameworkRegistry->getAdapters() as $adapter) {
                foreach ($adapter->getSupportedResponseClasses() as $responseClass) {
                    if ($shortName === $responseClass) {
                        return true;
                    }
                    if (isset($imports[$shortName]) && $imports[$shortName] === $responseClass) {
                        return true;
                    }
                }
            }
        }

        return false;
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

        foreach ($this->frameworkRegistry->getAdapters() as $frameworkAdapter) {
            $usedClass = $this->processClassInPath($path, $frameworkAdapter, $context->imports);
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
    private function processClassInPath(MethodPathDefinition $path, FrameworkAdapter $frameworkAdapter, array $imports): array
    {
        $usedClasses = $frameworkAdapter->getSupportedResponseClasses();

        /** @var ClassUsage[] */
        $usages = [];

        foreach ($path->getStatements() as $stmts) {
            $node = $stmts->getNode();

            foreach ($usedClasses as $usedClass) {
                $traverser = $this->classUsageVisitorFactory->create(
                    className: $usedClass,
                    imports: $imports
                );

                $this->processNode($node, $traverser);
                array_push($usages, ...$traverser->getUsages());
            }
        }

        return $usages;
    }

    private function processNode(Node $node, ClassUsageVisitor $traverser)
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
