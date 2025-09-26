<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Analyser\MethodPathAnalyser;
use Apilyser\Definition\ResponseBodyDefinition;
use Apilyser\Extractor\MethodPathExtractor;
use Apilyser\Traverser\ArrayKeyTraverser;
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
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeDumper;
use PhpParser\NodeTraverser;
use Symfony\Component\Console\Output\OutputInterface;

class TypeStructureResolver
{
    private VariableAssignmentFinder $variableAssignmentFinder;

    public function __construct(
        public OutputInterface $output,
        public NodeDumper $dumper,
        private MethodPathExtractor $methodPathExtractor,
        private ClassAstResolver $classAstResolver
    ) {
        $this->variableAssignmentFinder = new VariableAssignmentFinder();
    }

    /**
     * @param ClassMethodContext $context
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

        return $result() ?? [];
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
     * @param MethodCall $node
     * 
     * @return ?ResponseBodyDefinition[]
     */
    private function handleMethodCall(ClassMethodContext $context, array $methodJourney, MethodCall $node): array|null
    {
        $this->output->writeln("Test method call " . $this->dumper->dump($node));
        $nodeVar = $node->var;
        switch (true) {
            case $nodeVar instanceof Variable:
                $variableName = $nodeVar->name;

                /**
                 * In case that the method call is directly to another function (could be in same class or a different)
                 * we will need to find the called function. 
                 */
                if ($variableName == "this") {
                    $calledMethod = $this->classAstResolver->findMethodInClass($context->class, $node->name->name);
                    $methodPaths = $this->methodPathExtractor->analyse($calledMethod);

                    foreach ($methodPaths as $path) {

                    }
                }

                $nodeExpr = $this->variableAssignmentFinder->findAssignment($variableName, $methodJourney);
                $this->output->writeln("Method call variable: " . $this->dumper->dump($nodeExpr));
                if ($nodeExpr != null && $nodeExpr instanceof New_) {
                    $className = $nodeExpr->class->name;
                    if (is_string($className)) {
                        $classStructure = $this->classAstResolver->resolveClassStructure($className, $context->imports);
                        if ($classStructure != null) {

                            $calledMethod = $this->classAstResolver->findMethodInClass($classStructure->class, $node->name->name);
                            if ($calledMethod != null) {
                                $returnType = null;
                                if ($calledMethod->returnType instanceof NullableType) {
                                    $returnType = $calledMethod->returnType->type->name;
                                } else if ($calledMethod->returnType instanceof Identifier) {
                                    $returnType = $calledMethod->returnType->name;
                                }

                                $results = null;
                                if ($returnType == 'array') {
                                    $newContext = new ClassMethodContext(
                                        class: $classStructure->class,
                                        method: $calledMethod,
                                        imports: $context->imports
                                    );
                                    $results = $this->extractArray($newContext, $methodJourney, $node->name->name);
                                } else {
                                    $results = [];
                                }
                                
                                if ($results != null) {
                                    return $results;
                                }
                                return null;
                            }
                        }

                        return null;
                    }
                }
                break;

            case $nodeVar instanceof PropertyFetch:
                $variableName = $nodeVar->name;

                $property = $this->classAstResolver->findPropertyInClass($context->class, $nodeVar);
                if ($property != null) {
                    $classStructure = null;

                    if ($property->type instanceof NullableType && $property->type->type instanceof Name) {
                        $classStructure = $this->classAstResolver->resolveClassStructure($property->type->type->name, $context->imports);
                    } else if ($property->type instanceof Name) {
                        $classStructure = $this->classAstResolver->resolveClassStructure($property->type->name, $context->imports);
                    }

                    if ($classStructure != null) {
                        $calledMethod = $this->classAstResolver->findMethodInClass($classStructure->class, $node->name->name);

                        if ($calledMethod != null) {
                            $newContext = new ClassMethodContext(
                                class: $classStructure->class,
                                method: $calledMethod,
                                imports: $context->imports
                            );
                            $results = $this->extractArray($newContext, $methodJourney, $node->name->name);
                            if ($results != null) {
                                return $results;
                            }
                            return null;
                        }
                    }
                }
                break;
        }

        return null;
    }

    /**
     * @param ClassMethodContext $context
     * @param Array_ $node
     * 
     * @return ResponseBodyDefinition[]
     */
    private function handleArray(ClassMethodContext $context, array $methodJourney, Array_ $node): array
    {
        $resolvedItems = [];

        foreach ($node->items as $item) {
            $itemDef = $this->resolveArrayItemStructure($context, $methodJourney, $item);
            $resolvedItems[] = $itemDef;
        }

        return $resolvedItems;
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





        /*if ($typeNode instanceof NullableType) {
            // Property is Nullable
            if ($typeNode->type instanceof Identifier) {
                if ($typeNode->type->name == "array") {
                    $children = [];
                    foreach ($property->props as $prop) {
                        $children[] = $this->getBodyFromPropertyItem($context, $prop);
                    }

                    return new ResponseBodyDefinition(
                        name: $name,
                        type: $typeNode->type->name,
                        children: empty($children) ? null : $children,
                        nullable: true
                    );
                } else {
                    return new ResponseBodyDefinition(
                        name: $name,
                        type: $typeNode->type->name,
                        nullable: true
                    );
                }
            } else if ($typeNode->type instanceof Name) {
                // Custom object
                return new ResponseBodyDefinition(
                    name: $typeNode->type->name,
                    type: 'object',
                    nullable: true
                );
            }
        } else if ($typeNode instanceof Identifier) {
            // Property is simple class like 'string', 'int', 'array' etc.
            if ($property->type->name == "array") {
                $children = [];
                foreach ($property->props as $prop) {
                    $children = $this->getBodyFromPropertyItem($context, $prop);
                }

                return new ResponseBodyDefinition(
                    name: $name,
                    type: $property->type->name,
                    children: empty($children) ? null : $children,
                    nullable: false
                );
            } else {
                return new ResponseBodyDefinition(
                    name: $name,
                    type: $property->type->name,
                    nullable: false
                );
            }
        } else if ($typeNode instanceof Name) {
            // Property is custom object with namespace
            return new ResponseBodyDefinition(
                name: $property->type->name,
                type: 'object',
                nullable: true
            );
        } else {
            return null;
        }

        return null;*/
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
     * @return ResponseBodyDefinition
     */
    private function resolveArrayItemStructure(ClassMethodContext $context, array $methodJourney, ArrayItem $item): ResponseBodyDefinition
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
        if ($itemValue instanceof Scalar) {
            return new ResponseBodyDefinition(
                name: $itemKey,
                type: $itemValue->getType(),
                nullable: false
            );
        } else {
            $def = $this->resolveFromExpression($context, $methodJourney, $itemValue);
            return new ResponseBodyDefinition(
                name: $itemKey,
                type: 'array',
                children: empty($def) ? null : $def,
                nullable: false
            );
        }
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