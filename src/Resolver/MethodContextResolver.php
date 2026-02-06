<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Ast\VariableAssignmentFinder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;

class MethodContextResolver
{
    public function __construct(
        private ClassAstResolver $classAstResolver,
        private NodeFinder $nodeFinder,
        private VariableAssignmentFinder $variableAssignmentFinder
    ) {}

    /**
     * Responsible for finding the method and the class that it exists in, from a method call.
     *
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param MethodCall $node
     *
     * @return ClassMethodContext|null
     */
    public function resolve(ClassMethodContext $context, array $methodJourney, MethodCall $node): ?ClassMethodContext
    {
        $nodeVar = $node->var;

        return match (true) {
            $nodeVar instanceof Variable && $nodeVar->name === "this" => $this->resolveFromThisCall($context, $node),
            $nodeVar instanceof Variable => $this->resolveFromVariable($context, $methodJourney, $node, $nodeVar),
            $nodeVar instanceof PropertyFetch => $this->resolveFromPropertyFetch($context, $node, $nodeVar),
            default => null
        };
    }

    private function resolveFromThisCall(ClassMethodContext $context, MethodCall $node): ?ClassMethodContext
    {
        $calledMethod = $this->classAstResolver->findMethodInClass($context->class, $node->name->name);
        if (null === $calledMethod) {
            return null;
        }

        return new ClassMethodContext(
            namespace: $context->namespace,
            class: $context->class,
            method: $calledMethod,
            imports: $context->imports
        );
    }

    private function resolveFromVariable(
        ClassMethodContext $context,
        array $methodJourney,
        MethodCall $node,
        Variable $nodeVar
    ): ?ClassMethodContext {
        $nodeExpr = $this->variableAssignmentFinder->findAssignment($nodeVar->name, $methodJourney);

        $currentContext = null;
        $className = null;

        if (null === $nodeExpr) {
            // Looking for variable in function parameters
            $param = $this->nodeFinder->findFirst($context->method, function (Node $node) use ($nodeVar) {
                if ($node instanceof Param) {
                    if ($node->var instanceof Variable) {
                        if ($node->var->name === $nodeVar->name) {
                            return true;
                        }
                    }
                }

                return false;
            });

            if (null === $param) {
                return null;
            }

            if ($param->type instanceof Name) {
                $currentContext = $context;
                $className = $param->type->name;
            }
        }

        if ($nodeExpr instanceof MethodCall) {
            $newContext = $this->resolve($context, $methodJourney, $nodeExpr);
            if (null === $newContext) {
                return null;
            }

            $newClassStructure = $this->classAstResolver->resolveClassStructure(
                namespace: $newContext->namespace,
                className: $newContext->method->returnType->name,
                imports: $newContext->imports
            );

            $currentContext = $newContext;
            $className = $newClassStructure->class->name->name;
        }

        if ($nodeExpr instanceof New_) {
            $currentContext = $context;
            $className = $nodeExpr->class->name;
        }

        if (!is_string($className) || $currentContext === null) {
            return null;
        }

        $classStructure = $this->classAstResolver->resolveClassStructure($currentContext->namespace, $className, $currentContext->imports);
        if (null === $classStructure) {
            return null;
        }

        $calledMethod = $this->classAstResolver->findMethodInClass($classStructure->class, $node->name->name);
        if (null === $calledMethod) {
            return null;
        }

        return new ClassMethodContext(
            namespace: $classStructure->namespace,
            class: $classStructure->class,
            method: $calledMethod,
            imports: $classStructure->imports
        );
    }

    private function resolveFromPropertyFetch(
        ClassMethodContext $context,
        MethodCall $node,
        PropertyFetch $nodeVar
    ): ?ClassMethodContext {
        $classStructure = $this->findClassFromProperty($context, $nodeVar);
        if (null === $classStructure) {
            return null;
        }

        $calledMethod = $this->classAstResolver->findMethodInClass($classStructure->class, $node->name->name);
        if (null === $calledMethod) {
            return null;
        }

        return new ClassMethodContext(
            namespace: $classStructure->namespace,
            class: $classStructure->class,
            method: $calledMethod,
            imports: $context->imports
        );
    }

    private function findClassFromProperty(ClassMethodContext $context, PropertyFetch $nodeVar): ?ClassStructure
    {
        $property = $this->classAstResolver->findPropertyInClass($context->class, $nodeVar);
        if (null !== $property) {
            if ($property->type instanceof NullableType && $property->type->type instanceof Name) {
                return $this->classAstResolver->resolveClassStructure($context->namespace, $property->type->type->name, $context->imports);
            }

            if ($property->type instanceof Name) {
                return $this->classAstResolver->resolveClassStructure($context->namespace, $property->type->name, $context->imports);
            }

            return null;
        }

        $constructorParam = $this->classAstResolver->findConstructorParam($context->class, $nodeVar->name->name);
        if (null !== $constructorParam) {
            if ($constructorParam->type instanceof Name) {
                return $this->classAstResolver->resolveClassStructure($context->namespace, $constructorParam->type->name, $context->imports);
            }

            return null;
        }

        return null;
    }
}
