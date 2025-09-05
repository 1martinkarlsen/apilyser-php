<?php

namespace Apilyser\Definition;

use PhpParser\Node;

class MethodPathDefinition
{
    
    /** @var StatementInfoDefinition[] */
    private array $statements = [];

    /** @var ConditionInfoDefinition[] */
    private array $conditions = [];

    public function __construct()
    {}

    public function addStatement(Node $node)
    {
        $this->statements[] = new StatementInfoDefinition($node);
    }

    public function addCondition(string $type, ?Node $condition, bool $result)
    {
        $this->conditions[] = new ConditionInfoDefinition($type, $condition, $result);
    }

    public function getStatements(): array
    {
        return $this->statements;
    }
    
    public function getConditions(): array
    {
        return $this->conditions;
    }
    
}
