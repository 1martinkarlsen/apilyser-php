<?php

namespace Apilyser\Definition;

use PhpParser\Node;

class StatementInfoDefinition
{
    private $node;
    private $type;
    private $line;
    
    public function __construct(Node $node)
    {
        $this->node = $node;
        $this->type = $node->getType();
        $this->line = $node->getStartLine();
    }
    
    public function getNode(): Node
    {
        return $this->node;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getLine(): int
    {
        return $this->line ?? 0;
    }
}