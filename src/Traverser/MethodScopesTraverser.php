<?php

namespace Apilyser\Traverser;

use Apilyser\Extractor\MethodScope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeVisitorAbstract;

class MethodScopesTraverser extends NodeVisitorAbstract
{

    /**
     * @var MethodScope[]
     */
    public array $scopes = [];

    /**
     * @var Node[]
     */
    private $scopeExpressions = [
        If_::class,
        ElseIf_::class,
        Else_::class,
        Switch_::class,
        TryCatch::class,
    ];

    public function __construct() {}

    function enterNode(Node $node)
    {
        if (in_array(get_class($node), $this->scopeExpressions)) {
            $scope = new MethodScope($node);

            $parent = $this->lookForParentScope($node);
            if ($parent != null) {
                if (in_array(get_class($parent), $this->scopeExpressions)) {
                    $scope->scope = $parent;
                }
            }

            $this->scopes[] = $scope;
        } 

        return $node;
    }

    private function lookForParentScope(Node $node): ?Node
    {
        $parent = $node->getAttribute('parent');

        if ($parent) {
            if (in_array(get_class($parent), $this->scopeExpressions)) {
                return $parent;
            } else {
                return $this->lookForParentScope($parent);
            }
        }
        
        return null;
    }
}