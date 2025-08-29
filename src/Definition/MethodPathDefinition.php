<?php

namespace Apilyser\Definition;

use PhpParser\Node;

class MethodPathDefinition
{
    
    /** @var StatementInfo[] */
    private array $statements = [];

    /** @var ConditionInfo[] */
    private array $conditions = [];

    public function __construct()
    {}

    public function addStatement(Node $node)
    {
        $this->statements[] = new StatementInfo($node);
    }

    public function addCondition(string $type, ?Node $condition, bool $result)
    {
        $this->conditions[] = new ConditionInfo($type, $condition, $result);
    }

    public function getStatements(): array
    {
        return $this->statements;
    }
    
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getSummary(): array
    {
        return [
            'conditions' => array_map(fn($c) => $c->getSummary(), $this->conditions),
            'statements' => array_map(fn($s) => $s->getSummary(), $this->statements)
        ];
    }
    
}

class StatementInfo
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
    
    public function getSummary(): array
    {
        return [
            'type' => $this->type,
            'line' => $this->line,
            'description' => $this->getDescription()
        ];
    }
    
    private function getDescription(): string
    {
        $node = $this->node;
        
        switch ($this->type) {
            case 'Expr_Assign':
                return $this->describeAssignment($node);
            case 'Expr_MethodCall':
                return $this->describeMethodCall($node);
            case 'Expr_New':
                return $this->describeInstantiation($node);
            case 'Stmt_Return':
                return $this->describeReturn($node);
            default:
                return $this->type;
        }
    }
    
    private function describeAssignment(Node\Expr\Assign $assign): string
    {
        $var = $this->nodeToString($assign->var);
        $expr = $this->nodeToString($assign->expr);
        return "$var = $expr";
    }
    
    private function describeMethodCall(Node\Expr\MethodCall $call): string
    {
        $var = $this->nodeToString($call->var);
        $method = $call->name->name ?? 'unknown';
        $args = array_map(fn($arg) => $this->nodeToString($arg->value), $call->args);
        return "$var->$method(" . implode(', ', $args) . ")";
    }
    
    private function describeInstantiation(Node\Expr\New_ $new): string
    {
        $class = $this->nodeToString($new->class);
        $args = array_map(fn($arg) => $this->nodeToString($arg->value), $new->args);
        return "new $class(" . implode(', ', $args) . ")";
    }
    
    private function describeReturn(Node\Stmt\Return_ $return): string
    {
        if ($return->expr) {
            return "return " . $this->nodeToString($return->expr);
        }
        return "return";
    }
    
    private function nodeToString(Node $node): string
    {
        if ($node instanceof Node\Name) {
            return $node->toString();
        } elseif ($node instanceof Node\Expr\Variable) {
            return '$' . ($node->name ?? 'unknown');
        } elseif ($node instanceof Node\Scalar\String_) {
            return '"' . $node->value . '"';
        } elseif ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        } elseif ($node instanceof Node\Expr\Array_) {
            return '[...]';
        }
        
        return $node->getType();
    }
}

class ConditionInfo
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
    
    public function getSummary(): array
    {
        return [
            'type' => $this->type,
            'result' => $this->result,
            'description' => $this->getDescription()
        ];
    }
    
    private function getDescription(): string
    {
        if (!$this->condition) {
            return $this->type;
        }
        
        $condStr = $this->nodeToString($this->condition);
        $resultStr = $this->result ? 'true' : 'false';
        
        return "{$this->type}: $condStr = $resultStr";
    }
    
    private function nodeToString(Node $node): string
    {
        // Simplified node-to-string conversion
        if ($node instanceof Node\Expr\BinaryOp\Greater) {
            $left = $this->nodeToString($node->left);
            $right = $this->nodeToString($node->right);
            return "$left > $right";
        } elseif ($node instanceof Node\Expr\BinaryOp\Equal) {
            $left = $this->nodeToString($node->left);
            $right = $this->nodeToString($node->right);
            return "$left == $right";
        } elseif ($node instanceof Node\Expr\Variable) {
            return '$' . ($node->name ?? 'unknown');
        } elseif ($node instanceof Node\Scalar\String_) {
            return '"' . $node->value . '"';
        } elseif ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        }
        
        return $node->getType();
    }
}
