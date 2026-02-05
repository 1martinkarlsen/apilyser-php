<?php declare(strict_types=1);

namespace Apilyser\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;

class ArrayKeyVisitor extends NodeVisitorAbstract
{
    /** @var \PhpParser\Node\ArrayItem[] */
    private array $arrayKeys = [];
    private bool $inTargetMethod = false;
    private string $methodName;

    public function __construct(string $methodName)
    {
        $this->methodName = $methodName;
    }

    public function enterNode(Node $node)
    {
        // Check if we're entering the target method
        if ($node instanceof ClassMethod && $node->name->toString() === $this->methodName) {
            $this->inTargetMethod = true;
        }

        // If we're in the target method and found an array item with a string key
        if ($this->inTargetMethod && $node instanceof ArrayItem && $node->key instanceof String_) {
            $this->arrayKeys[] = $node;
        }

        // Alternative case for direct string keys (not wrapped in quotes in the source)
        if ($this->inTargetMethod && $node instanceof ArrayItem && $node->key instanceof Int_) {
            $this->arrayKeys[] = $node;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        // When exiting the method, reset the flag
        if ($node instanceof ClassMethod && $node->name->toString() === $this->methodName) {
            $this->inTargetMethod = false;
        }

        return null;
    }

    /**
     * @return \PhpParser\Node\ArrayItem[]
     */
    public function getArrayItems(): array
    {
        if (empty($this->arrayKeys)) {
            return [];
        } else {
            return $this->arrayKeys;
        }
    }
}
