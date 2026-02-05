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
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class TypeStructureResolver
{
    private VariableAssignmentFinder $variableAssignmentFinder;

    public function __construct(
        private ClassAstResolver $classAstResolver,
        private NodeFinder $nodeFinder
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
     * Responsible for finding the method and the class that it exist in, from a method call.
     * 
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

                $currentContext = null;
                $className = null;
                if (null === $nodeExpr) {
                    // Looking for userRepository is assigned. It's not found so we have to look in function parameters
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
                    $newContext = $this->findMethodContext($context, $methodJourney, $nodeExpr);
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

                $returnType = $this->mapReturnToTypeName($returnType);

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
            case $itemValue instanceof Variable:
                return $this->handleVariableArrayItem($context, $methodJourney, $itemValue, $itemKey);
        }

        return null;
    }

    /**
     * Handles a variable in an array item - checks both local scope and class scope
     * 
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Variable $variable
     * @param string|int|null $itemKey
     * 
     * @return ResponseBodyDefinition|null
     */
    private function handleVariableArrayItem(
        ClassMethodContext $context, 
        array $methodJourney, 
        Variable $variable, 
        string|int|null $itemKey
    ): ?ResponseBodyDefinition
    {
        $variableName = $variable->name;
    
        // 1. First, check for local variable assignment in method scope
        $assignedExpr = $this->variableAssignmentFinder->findAssignment($variableName, $methodJourney);
        
        if ($assignedExpr !== null) {
            // Variable is assigned in method - resolve its expression
            return $this->resolveVariableFromExpression($context, $methodJourney, $assignedExpr, $itemKey);
        }
        
        // 2. Check if it's a class property ($this->propertyName)
        // We need to construct a PropertyFetch to search for it
        $propertyFetch = new PropertyFetch(
            new Variable('this'),
            new Identifier($variableName)
        );
        
        $property = $this->classAstResolver->findPropertyInClass($context->class, $propertyFetch);
        
        if ($property !== null) {
            // Found as class property
            return $this->getBodyFromProperty($context, $methodJourney, $property, $itemKey ?? $variableName);
        }
        
        // 3. Check if it's a constructor parameter (promoted property or injected dependency)
        $constructorParam = $this->classAstResolver->findConstructorParam($context->class, $variableName);
        
        if ($constructorParam !== null) {
            return $this->getBodyFromConstructorParam($constructorParam, $itemKey ?? $variableName);
        }
        
        // 4. Variable not found anywhere - return unknown
        return new ResponseBodyDefinition(
            name: $itemKey ?? $variableName,
            type: null,
            nullable: true
        );
    }

    /**
     * Resolves a variable's type from its assigned expression
     * 
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Expr $expr
     * @param string|int|null $itemKey
     * 
     * @return ResponseBodyDefinition|null
     */
    private function resolveVariableFromExpression(
        ClassMethodContext $context,
        array $methodJourney,
        Expr $expr,
        string|int|null $itemKey
    ): ?ResponseBodyDefinition
    {
        switch (true) {
            case $expr instanceof Scalar:
                $typeName = $this->getScalarTypeName($expr);
                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: $typeName,
                    nullable: false
                );
                
            case $expr instanceof Array_:
                $def = $this->resolveFromExpression($context, $methodJourney, $expr);
                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: 'array',
                    children: empty($def) ? null : $def,
                    nullable: false
                );
                
            case $expr instanceof MethodCall:
                $def = $this->handleMethodCall($context, $methodJourney, $expr);
                return new ResponseBodyDefinition(
                    name: $itemKey,
                    type: 'array',
                    children: empty($def) ? null : $def,
                    nullable: false
                );
                
            case $expr instanceof New_:
                if ($expr->class instanceof Name) {
                    return new ResponseBodyDefinition(
                        name: $itemKey,
                        type: 'object',
                        nullable: false
                    );
                }
                break;
                
            case $expr instanceof Variable:
                // Recursive call - variable assigned to another variable
                return $this->handleVariableArrayItem($context, $methodJourney, $expr, $itemKey);
        }
        
        return null;
    }

    /**
     * Gets body definition from a constructor parameter
     * 
     * @param Param $param
     * @param string|int $name
     * 
     * @return ResponseBodyDefinition
     */
    private function getBodyFromConstructorParam(
        Param $param,
        string|int $name
    ): ResponseBodyDefinition
    {
        $isNullable = false;
        $paramType = $param->type;
        
        if ($paramType instanceof NullableType) {
            $isNullable = true;
            $paramType = $paramType->type;
        }
        
        if ($paramType instanceof Identifier) {
            // Simple type like 'string', 'int', 'array'
            return new ResponseBodyDefinition(
                name: $name,
                type: $paramType->name,
                nullable: $isNullable
            );
        }
        
        if ($paramType instanceof Name) {
            // Custom class type
            return new ResponseBodyDefinition(
                name: $name,
                type: 'object',
                nullable: $isNullable
            );
        }
        
        // No type hint
        return new ResponseBodyDefinition(
            name: $name,
            type: 'mixed',
            nullable: true
        );
    }

    private function getScalarTypeName(Scalar $scalar): string
    {
        // true/false are not Scalar nodes, they are ConstFetch nodes.
        // This function only handles actual scalar literals.
        return match (true) {
            $scalar instanceof String_ => 'string',
            $scalar instanceof Int_ => 'integer',
            $scalar instanceof Float_ => 'float',
            default => 'mixed'
        };
    }

    private function mapReturnToTypeName(string $return): string
    {
        return match (true) {
            $return === 'string' => 'string',
            $return === 'int' => 'integer',
            $return === 'float' => 'float',
            $return === 'array' => 'array',
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
        $traverser = new NodeTraverser();
        $keyExtractor = new ArrayKeyTraverser($calledMethodName);
        $traverser->addVisitor($keyExtractor);
        $traverser->traverse($context->class->stmts);
        
        $items = $keyExtractor->getArrayItems();

        $bodyContent = [];
        foreach ($items as $item) {

            $body = $this->findValueType($context, $methodJourney, $item->value, $item->key);
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
    private function findValueType(ClassMethodContext $context, array $methodJourney, Expr $value, ?Expr $key = null): ?ResponseBodyDefinition
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
                        type: $this->mapReturnToTypeName($res->getType()),
                        children: $children,
                        nullable: $res->getIsNullable()
                    );

                    return $result;
                }

                return null;
            case $value instanceof Scalar:
                $name = null;
                if ($key instanceof String_) {
                    $name = $key->value;
                }
                $typeName = $this->getScalarTypeName($value);
                return new ResponseBodyDefinition(
                    name: $name,
                    type: $typeName,
                    nullable: false
                );
        }

        return null;
    }

}