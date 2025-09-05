<?php

namespace Apilyser\Definition;

use PhpParser\Node;

class ConditionInfoDefinition
{
    private $type;
    private $condition;
    private $result;
    
    public function __construct(string $type, ?Node $condition, bool $result)
    {
        $this->type = $type;
        $this->condition = $condition;
        $this->result = $result;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getCondition(): ?Node
    {
        return $this->condition;
    }
    
    public function getResult(): bool
    {
        return $this->result;
    }
}
