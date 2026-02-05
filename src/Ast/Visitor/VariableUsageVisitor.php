<?php declare(strict_types=1);

namespace Apilyser\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

class VariableUsageVisitor extends NodeVisitorAbstract
{

    /**
     * @var Node[]
     */
    private $usages = [];

    public function __construct(
        public string $variableName,
        private bool $findRootParent = true
    ) {}

    public function enterNode(Node $node)
    {
        if ($node instanceof Variable
            && is_string($node->name)
            && $node->name === $this->variableName) {

            $this->usages[] = $this->lookForParent($node);
        }

        return null;
    }

    /**
     * @return Node[]
     */
    public function getUsages(): array
    {
        return $this->usages;
    }

    private function lookForParent(Node $node): Node
    {
        $parent = $node->getAttribute('parent');

        if ($parent) {
            if ($this->findRootParent) {
                return $this->lookForParent($parent);
            } else {
                return $parent;
            }
        }

        return $node;
    }
}
