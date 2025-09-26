<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

class VariableAssignmentFinder
{

    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @param string $variableName
     * @param Node[] $nodes
     * 
     * @return ?Expr
     */
    public function findAssignment(string $variableName, array $nodes): ?Expr
    {
        if ($nodes === null) {
            return null;
        }

        $variableNodes = $this->nodeFinder->find($nodes, function(Node $node) use ($variableName) {
            return $this->isVariableAssignment($node, $variableName);
        });

        $node = end($variableNodes);

        if ($node instanceof Assign) {
            return $node->expr;
        }

        return null;
    }

    

    private function isVariableAssignment(Node $node, string $variableName)
    {
        if ($node instanceof Assign) {
            if ($node->var instanceof Variable) {
                if ($node->var->name == $variableName) {
                    return true;
                }
            }
        }

        return false;
    }
}