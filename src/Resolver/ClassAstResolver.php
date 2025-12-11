<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Parser\NodeParser;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_ as StmtConst_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;

class ClassAstResolver
{
    
    private NodeFinder $nodeFinder;

    public function __construct(
        private NamespaceResolver $namespaceResolver,
        private NodeParser $nodeParser
    ) {
        $this->nodeFinder = new NodeFinder();  
    }

    /**
     * Resolves a class's AST and its imports into a ClassStructure.
     *
     * @param string $className The short class name (e.g., 'Post').
     * @param array<string, string> $imports The imports from the current file.
     * @return ClassStructure|null
     */
    public function resolveClassStructure(Namespace_ $namespace, string $className, array $imports): ?ClassStructure
    {
        $fullClassName = $this->namespaceResolver->findFullNamespaceForClass(
            className: $className,
            imports: $imports,
            currentNamespace: $namespace
        );

        // If the class name is not fully qualified (i.e., it was not in the imports),
        // we assume it's in the same namespace.
        if (strpos($fullClassName, '\\') === false) {
            $fullClassName = $namespace->name->name . "\\" . $className;
        }

        $filePath = $this->namespaceResolver->resolveNamespace($fullClassName);
        if (is_string($filePath) && file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $stmts = $this->nodeParser->parse($content);

            $namespace = $this->nodeFinder->findFirstInstanceOf($stmts, Namespace_::class);
            $classImports = $this->nodeFinder->findInstanceOf($stmts, Use_::class);
            foreach ($classImports as $useNamespace) {
                foreach ($useNamespace->uses as $use) {
                    $alias = $use->alias ? $use->name->name : $use->name->getLast();
                    $imports[$alias] = $use->name->name;
                }
            }

            $classNode = $this->nodeFinder->findFirst($stmts, function (Node $node) use ($className) {
                if ($node instanceof Class_) {
                    if ($node->name->name === $className) {
                        return $node;
                    }
                }

                return null;
            });

            if ($classNode instanceof Class_) {
                return new ClassStructure(
                    namespace: $namespace,
                    imports: $classImports,
                    class: $classNode
                );
            }
        }

        return null;
    }

    /**
     * Finds a specific method within a class's AST.
     * 
     * @param Class_ $class The class to search in.
     * @param string $methodName The name of the method to find.
     * @return ClassMethod|null
     */
    public function findMethodInClass(Class_ $class, string $methodName): ?ClassMethod 
    {
        /** @var ClassMethod|null */
        $method = $this->nodeFinder->findFirst($class->stmts, function (Node $nodeItem) use ($methodName) {
            return $nodeItem instanceof ClassMethod && $nodeItem->name->name === $methodName;
        });

        return $method;
    }

    /**
     * Finds a specific property within a class's AST.
     *
     * @param Class_ $class The class AST.
     * @param PropertyFetch $propertyFetch The property fetch node.
     * @return Property|null
     */
    public function findPropertyInClass(Class_ $class, PropertyFetch $propertyFetch): ?Property
    {
        $propertyName = $propertyFetch->name->name;

        /** @var Property|null */
        $property = $this->nodeFinder->findFirst($class->stmts, function (Node $node) use ($propertyName) {
            return $node instanceof Property && 
                isset($node->props[0]) && 
                $node->props[0]->name->name == $propertyName;
        });

        return $property;
    }


    /**
     * Finds a specific injected param in the constructor
     * 
     * @param Class_ $class The class to search in.
     * @param string $paramName The name of the param to find.
     * @return Param|null
     */
    public function findConstructorParam(Class_ $class, string $paramName): ?Param
    {
        // Property doesn't exist on class level so we will look in constructor
        $constructorMethod = $this->nodeFinder->findFirst($class->stmts, function (Node $node) {
            return $node instanceof ClassMethod && $node->name->name == '__construct';
        });

        if (null === $constructorMethod) {
            return null;
        }

        $propertyParam = null;
        foreach ($constructorMethod->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $paramName) {
                $propertyParam = $param;
                break;
            }
        }

        return $propertyParam;
    }

    public function findConstInClass(Class_ $class, string $constName): ?Const_
    {
        return $this->nodeFinder->findFirst($class->stmts, function (Node $node) use ($constName) {
            return $node instanceof Const_ && $node->name->name === $constName;
        });
    }
}