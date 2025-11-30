<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Definition\ResponseBodyDefinition;
use Apilyser\Traverser\ArrayKeyTraverser;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use Symfony\Component\Console\Output\OutputInterface;

class TypeStructureResolver
{
    private VariableAssignmentFinder $variableAssignmentFinder;

    public function __construct(
        private OutputInterface $output,
        private ClassAstResolver $classAstResolver
    ) {
        $this->variableAssignmentFinder = new VariableAssignmentFinder();
    }

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Expr $expr
     * 
     * @return ResponseBodyDefinition[]
     */
    public function resolveFromExpression(ClassMethodContext $context, array $methodJourney, Expr $expr): array
    {
        $result = match (true) {
            $expr instanceof MethodCall => fn() => $this->handleMethodCall($context, $methodJourney, $expr),
            $expr instanceof Variable => fn() => $this->handleVariable($context, $methodJourney, $expr->name),
            $expr instanceof Array_ => fn() => $this->handleArray($context, $methodJourney, $expr),
            default => fn() => []
        };

        return array_unique($result() ?? []);
    }

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param string $variableName
     * 
     * @return ResponseBodyDefinition[]
     */
    private function handleVariable(ClassMethodContext $context, array $methodJourney, string $variableName): array
    {
        $assignedExpr = $this->variableAssignmentFinder->findAssignment($variableName, $methodJourney);
        
        if ($assignedExpr != null) {
            return $this->resolveFromExpression($context, $methodJourney, $assignedExpr);
        }

        return [];
    }

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param MethodCall $node
     * 
     * @return ?ResponseBodyDefinition[]
     */
    private function handleMethodCall(ClassMethodContext $context, array $methodJourney, MethodCall $node): array|null
    {
        $newMethodContext = $this->findMethodContext($context, $methodJourney, $node);

        if (null === $newMethodContext) {
            return null;
        }

        $returnType = null;
        if ($newMethodContext->method->returnType instanceof NullableType) {
            $returnType = $newMethodContext->method->returnType->type->name;
        } else if ($newMethodContext->method->returnType instanceof Identifier) {
            $returnType = $newMethodContext->method->returnType->name;
        }

        $results = [];
        if ($returnType === 'array') {
            // TODO: Issue here, becuase extractArray is mapping an array but because the function
            // is calling another function which returns an array, we need to handle method call.
            $results = $this->extractArray($newMethodContext, $methodJourney, $node->name->name);
        } else {
            // Simple return types like 'int', 'string' etc.
            // This is wrong
            $result = $this->findValueType($newMethodContext, $methodJourney, $node->var);
            $results[] = $result; 
        }

        if ($results != null) {
            return $results;
        }

        return null;
    }

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Array_ $node
     * 
     * @return ResponseBodyDefinition[]
     */
    private function handleArray(ClassMethodContext $context, array $methodJourney, Array_ $node): array
    {
        $resolvedItems = [];

        foreach ($node->items as $item) {
            $itemDef = $this->resolveArrayItemStructure($context, $methodJourney, $item);
            if ($itemDef !== null) {
                $resolvedItems[] = $itemDef;
            }
        }

        return array_unique($resolvedItems);
    }

    /**
     * @return ClassMethodContext|null
     */
    private function findMethodContext(ClassMethodContext $context, array $methodJourney, MethodCall $node)
    {
        $nodeVar = $node->var;

        $result = match (true) {
            $nodeVar instanceof Variable && $nodeVar->name === "this" => fn() => function ($context, $node, $nodeVar, $methodJourney) {
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
            },
            $nodeVar instanceof Variable => fn() => function($context, $node, $nodeVar, $methodJourney) {
                $nodeExpr = $this->variableAssignmentFinder->findAssignment($nodeVar->name, $methodJourney);
                if (null === $nodeExpr || !($nodeExpr instanceof New_)) {
                    return null;
                }

                $className = $nodeExpr->class->name;
                if (!is_string($className)) {
                    return null;
                }

                $classStructure = $this->classAstResolver->resolveClassStructure($context->namespace, $className, $context->imports);
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
            },
            $nodeVar instanceof PropertyFetch => fn() => function($context, $node, $nodeVar, $methodJourney) {
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
            },
            default => fn() => null
        };

        return $result()($context, $node, $nodeVar, $methodJourney) ?? null;
    }

    private function findClassFromProperty(ClassMethodContext $context, PropertyFetch $nodeVar): ?ClassStructure
    {
        $property = $this->classAstResolver->findPropertyInClass($context->class, $nodeVar);
        if (null !== $property) {
            // Handle property
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
            // Handle constructor param
            if ($constructorParam->type instanceof Name) {
                return $this->classAstResolver->resolveClassStructure($context->namespace, $constructorParam->type->name, $context->imports);
            }

            return null;
        }

        return null;
    }

    /**
     * @param ClassMethodContext $context
     * @param Property $property
     * @param string $name
     * 
     * @return ResponseBodyDefinition|null
     */
    private function getBodyFromProperty(ClassMethodContext $context, array $methodJourney, Property $property, string $name): ?ResponseBodyDefinition
    {
        $typeNode = $property->type;
        $isNullable = false;

        $propertyChildType = $typeNode;
        if ($typeNode instanceof NullableType) {
            $isNullable = true;
            $propertyChildType = $typeNode->type;
        }

        // Property is simple class like 'string', 'int', 'array' etc.
        if ($propertyChildType instanceof Identifier) {
            $typeName = $propertyChildType->name;
            $children = null;

            if ($typeName === "array") {
                $children = [];
                foreach ($property->props as $prop) {
                    $itemChildren = $this->getBodyFromPropertyItem($context, $methodJourney, $prop);
                    $children = array_merge($children, $itemChildren);
                }
            }

            return new ResponseBodyDefinition(
                name: $name,
                type: $typeName,
                children: empty($children) ? null : $children,
                nullable: $isNullable
            );
        }

        // Custom object
        if ($propertyChildType instanceof Name) {
            return new ResponseBodyDefinition(
                name: $name,
                type: 'object',
                nullable: $isNullable
            );
        }

        return null;
    }

    /**
     * @param ClassMethodContext $context
     * @param PropertyItem $prop
     * 
     * @return ResponseBodyDefinition[]
     */
    private function getBodyFromPropertyItem(ClassMethodContext $context, array $methodJourney, PropertyItem $prop): array
    {
        // TODO: `findVariableAssignmentInMethod` is not working if the property exist on a class level
        // or if it's set in another method (ex constructor).
        $assignment = $this->variableAssignmentFinder->findAssignment($prop->name->name, $methodJourney);
        if ($assignment != null) {
            $res = $this->resolveFromExpression($context, $methodJourney, $assignment);
            return $res;
        }

        return [];
    }

    /**
     * @param ClassMethodContext $context
     * @param ArrayItem $item
     * 
     * @return ResponseBodyDefinition|null
     */
    private function resolveArrayItemStructure(ClassMethodContext $context, array $methodJourney, ArrayItem $item): ?ResponseBodyDefinition
    {
        $itemKey = null;
        switch (true) {
            case $item->key instanceof String_:
            case $item->key instanceof Int_:
                // array representing properties (e.g. ['id' => 1])
                $itemKey = $item->key->value;
                break;

            case $item->key == null:
                // array of strings, objects, etc.
                $itemKey = null;
                break;
        }

        $itemValue = $item->value;
        switch (true) {
            case $itemValue instanceof Scalar:
                $typeName = $this->getScalarTypeName($itemValue);
                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: $typeName,
                    nullable: false
                );

            case $itemValue instanceof MethodCall:
                if (null === $itemKey || empty($itemKey)) {
                    // If no key, it must be an array
                    $def = $this->resolveFromExpression($context, $methodJourney, $itemValue);
                    return new ResponseBodyDefinition(
                        name: $itemKey,
                        type: 'array',
                        children: empty($def) ? null : $def,
                        nullable: false
                    );
                }
                
                $methodCallContext = $this->findMethodContext($context, $methodJourney, $itemValue);
                if (null === $methodCallContext) {
                    return null;
                }

                $returnType = null;
                if ($methodCallContext->method->returnType instanceof NullableType) {
                    $returnType = $methodCallContext->method->returnType->type->name;
                } else if ($methodCallContext->method->returnType instanceof Identifier) {
                    $returnType = $methodCallContext->method->returnType->name;
                }

                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: $returnType,
                    children: [],
                    nullable: false
                );

            case $itemValue instanceof Array_:
                $def = $this->resolveFromExpression($context, $methodJourney, $itemValue);
                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: 'array',
                    children: empty($def) ? null : $def,
                    nullable: false
                );
        }

        return null;
    }

    private function getScalarTypeName(Scalar $scalar): string
    {
        // true/false are not Scalar nodes, they are ConstFetch nodes.
        // This function only handles actual scalar literals.
        return match (true) {
            $scalar instanceof String_ => 'string',
            $scalar instanceof LNumber => 'int',
            $scalar instanceof DNumber => 'float',
            default => 'mixed'
        };
    }

    /**
     * @param ClassMethodContext $context
     * @param string $calledMethodName
     * 
     * @return ResponseBodyDefinition[]
     */
    private function extractArray(ClassMethodContext $context, array $methodJourney, string $calledMethodName): array
    {
        $this->output->writeln("Class -> " . $context->class->name);
        $this->output->writeln("Method -> " . $context->method->name->name);

        $traverser = new NodeTraverser();
        $keyExtractor = new ArrayKeyTraverser($calledMethodName);
        $traverser->addVisitor($keyExtractor);
        $traverser->traverse($context->class->stmts);
        
        $items = $keyExtractor->getArrayItems();

        $bodyContent = [];
        foreach ($items as $item) {
            $body = $this->findValueType($context, $methodJourney, $item->value);
            if ($body != null) {
                array_push($bodyContent, $body);
            }
        }
        return $bodyContent;
    }

    /**
     * @param ClassMethodContext $context
     * @param Expr $value
     * 
     * @return ResponseBodyDefinition|null
     */
    private function findValueType(ClassMethodContext $context, array $methodJourney, Expr $value): ?ResponseBodyDefinition
    {
        switch (true) {
            case $value instanceof PropertyFetch:
                $property = $this->classAstResolver->findPropertyInClass($context->class, $value);

                if ($property != null) {
                    return $this->getBodyFromProperty($context, $methodJourney, $property, $value->name->name);
                }
                
                break;
            case $value instanceof MethodCall:
                $children =  $this->handleMethodCall($context, $methodJourney, $value);

                $res =  $this->findValueType($context, $methodJourney, $value->var);
                if ($res != null && $value->var instanceof PropertyFetch) {
                    $result = new ResponseBodyDefinition(
                        name: $value->var->name->name,
                        type: $res->getType(),
                        children: $children,
                        nullable: $res->getIsNullable()
                    );

                    return $result;
                }

                return null;
        }

        return null;
    }

}