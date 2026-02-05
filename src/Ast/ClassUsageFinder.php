<?php declare(strict_types=1);

namespace Apilyser\Ast;

use Apilyser\Ast\Visitor\ClassUsageVisitorFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Used for extracting usage of classes provided by FrameworkRegistry
 */
class ClassUsageFinder
{
    public function __construct(private ClassUsageVisitorFactory $classUsageVisitorFactory)
    {
    }

    /**
     * @param Node[] $stmts
     * @param string[] $imports
     *
     * @return ClassUsage[]
     */
    function extract(array $stmts, string $className, array $imports): array
    {
        $result = [];

        $usages = $this->traverseResponse(
            className: $className,
            stmts: $stmts,
            imports: $imports
        );

        array_push(
            $result,
            ...$usages
        );

        return $result;
    }

    /**
     * @param string $className
     * @param Node[] $stmts
     * @param string[] $imports
     *
     * @return ClassUsage[]
     */
    private function traverseResponse(string $className, array $stmts, array $imports)
    {
        // Establish parent relationships
        $traverser = new NodeTraverser();
        $parentConnector = new ParentConnectingVisitor();
        $traverser->addVisitor($parentConnector);
        $ast = $traverser->traverse($stmts);

        // Find class usage
        $tt = new NodeTraverser();
        $finder = $this->classUsageVisitorFactory->create(
            className: $className,
            imports: $imports
        );
        $tt->addVisitor($finder);
        $tt->traverse($ast);

        return $finder->getUsages();
    }
}
