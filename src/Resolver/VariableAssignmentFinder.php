<?php

namespace Apilyser\Resolver;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeFinder;

class VariableAssignmentFinder
{

    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function findAssignment(string $variableName, ClassMethod $method): ?Expr
    {
        if ($method->stmts === null) {
            return null;
        }

        $node = $this->nodeFinder->findFirst($method->stmts, function(Node $node) use ($variableName) {
            return $this->isVariableAssignment($node, $variableName);
        });

        if ($node instanceof Assign) {
            return $node->expr;
        }

        return null;
    }

    

    private function isVariableAssignment(Node $node, string $variableName)
    {
        if ($node instanceof Assign) {
            if ($node->var instanceof Variable || $node->var instanceof VarLikeIdentifier) {
                if ($node->var->name == $variableName) {
                    return true;
                }
            }
        }

        return false;
    }
}