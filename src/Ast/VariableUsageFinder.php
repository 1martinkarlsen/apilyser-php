<?php declare(strict_types=1);

namespace Apilyser\Ast;

use Apilyser\Ast\Visitor\VariableUsageVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Used for finding usages of specific variables.
 */
class VariableUsageFinder {

    public function __construct() {}

    /**
     * @param Variable $node
     * @param ClassMethod $method
     *
     * @return Node[]
     */
    public function extractVariableUsage(Variable $node, ClassMethod $method): array
    {
        return $this->traverseRequestUsage($method->stmts, $node->name);
    }

    /**
     * @return Node[]
     */
    private function traverseRequestUsage(array $stmts, string $name): array
    {
        // Establish parent relationships
        $traverser = new NodeTraverser();
        $parentConnector = new ParentConnectingVisitor();
        $traverser->addVisitor($parentConnector);
        $ast = $traverser->traverse($stmts);

        // Find variable usages
        $tt = new NodeTraverser();
        $usageFinder = new VariableUsageVisitor(variableName: $name, findRootStmt: false);
        $tt->addVisitor($usageFinder);
        $tt->traverse($ast);
        $usages = $usageFinder->getUsages();

        return $usages;
    }
}
