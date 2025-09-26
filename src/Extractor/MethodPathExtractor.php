<?php

namespace Apilyser\Extractor;

use Apilyser\Definition\MethodPathDefinition;
use PhpParser\Node;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;

class MethodPathExtractor
{

    /** @var MethodPathDefinition[] */
    private array $paths = [];

    /**
     * @param ClassMethod $method
     * 
     * @return MethodPathDefinition[]
     */
    public function extract(ClassMethod $method): array
    {
        $this->paths = [];
        $this->analyseStatements($method->stmts, new MethodPathDefinition());

        return $this->paths;
    }

    private function analyseStatements(array $stmts, MethodPathDefinition $currentPath): void
    {
        for ($i = 0; $i < count($stmts); $i++) {
            $stmt = $stmts[$i];
            $newPath = clone $currentPath;
            
            // Record this statement in the path
            $newPath->addStatement($stmt);
            
            switch (true) {
                case $stmt instanceof If_:
                    // Get remaining statements after the if block
                    $remainingStmts = array_slice($stmts, $i + 1);
                    $this->handleConditional($stmt, $newPath, $remainingStmts);
                    return; // All paths handled in conditional
                    
                case $stmt instanceof Return_:
                    $this->paths[] = $newPath;
                    return; // Path ends here
                    
                case $stmt instanceof Throw_:
                    $this->paths[] = $newPath;
                    return; // Path ends here
                    
                case $stmt instanceof While_:
                case $stmt instanceof For_:
                case $stmt instanceof Foreach_:
                    $remainingStmts = array_slice($stmts, $i + 1);
                    $this->handleLoop($stmt, $newPath, $remainingStmts);
                    return;
                    
                case $stmt instanceof Switch_:
                    $remainingStmts = array_slice($stmts, $i + 1);
                    $this->handleSwitch($stmt, $newPath, $remainingStmts);
                    return;
                    
                default:
                    $currentPath = $newPath;
            }
        }
        
        // If we reach the end without explicit termination
        $this->paths[] = $currentPath;
    }

    private function handleConditional(Node\Stmt\If_ $ifStmt, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        // True branch
        $truePath = clone $basePath;
        $truePath->addCondition("if", $ifStmt->cond, true);
        $this->analyseStatements($ifStmt->stmts, $truePath);
        
        // Continue with remaining statements after if block (if no return/throw)
        if (!$this->pathEndsWithTermination($ifStmt->stmts)) {
            $this->analyseStatements($remainingStmts, $truePath);
        }
        
        // Handle elseif chains
        foreach ($ifStmt->elseifs as $elseif) {
            $elseifPath = clone $basePath;
            $elseifPath->addCondition("elseif", $elseif->cond, true);
            $this->analyseStatements($elseif->stmts, $elseifPath);
            
            // Continue with remaining statements after elseif block
            if (!$this->pathEndsWithTermination($elseif->stmts)) {
                $this->analyseStatements($remainingStmts, $elseifPath);
            }
        }
        
        // Else branch (or implicit else if no else block)
        if ($ifStmt->else) {
            $elsePath = clone $basePath;
            $elsePath->addCondition("else", $ifStmt->cond, false);
            $this->analyseStatements($ifStmt->else->stmts, $elsePath);
            
            // Continue with remaining statements after else block
            if (!$this->pathEndsWithTermination($ifStmt->else->stmts)) {
                $this->analyseStatements($remainingStmts, $elsePath);
            }
        } else {
            // Implicit else path (condition was false, continue after if)
            $elsePath = clone $basePath;
            $elsePath->addCondition("implicit-else", $ifStmt->cond, false);
            $this->analyseStatements($remainingStmts, $elsePath);
        }
    }

    private function handleLoop(Node $loopStmt, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        // Path that enters the loop
        $loopPath = clone $basePath;
        $loopPath->addCondition("loop-enter", $this->getLoopCondition($loopStmt), true);
        
        if ($loopStmt instanceof Node\Stmt\While_) {
            $this->analyseStatements($loopStmt->stmts, $loopPath);
        } elseif ($loopStmt instanceof Node\Stmt\For_) {
            $this->analyseStatements($loopStmt->stmts, $loopPath);
        } elseif ($loopStmt instanceof Node\Stmt\Foreach_) {
            $this->analyseStatements($loopStmt->stmts, $loopPath);
        }
        
        // Continue with remaining statements after loop (if no break/return)
        if (!$this->pathEndsWithTermination($loopStmt->stmts)) {
            $this->analyseStatements($remainingStmts, $loopPath);
        }
        
        // Path that skips the loop
        $skipPath = clone $basePath;
        $skipPath->addCondition("loop-skip", $this->getLoopCondition($loopStmt), false);
        $this->analyseStatements($remainingStmts, $skipPath);
    }

    private function handleSwitch(Node\Stmt\Switch_ $switchStmt, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        $hasDefaultCase = false;
        
        foreach ($switchStmt->cases as $case) {
            $casePath = clone $basePath;
            if ($case->cond) {
                $casePath->addCondition("case", $case->cond, true);
            } else {
                $casePath->addCondition("default", null, true);
                $hasDefaultCase = true;
            }
            
            $this->analyseStatements($case->stmts, $casePath);
            
            // Continue with remaining statements after switch (if no break/return)
            if (!$this->pathEndsWithTermination($case->stmts)) {
                $this->analyseStatements($remainingStmts, $casePath);
            }
        }
        
        // If no default case, create a path that doesn't match any case
        if (!$hasDefaultCase) {
            $noMatchPath = clone $basePath;
            $noMatchPath->addCondition("no-case-match", null, false);
            $this->analyseStatements($remainingStmts, $noMatchPath);
        }
    }

    private function getLoopCondition(Node $loopStmt): ?Node
    {
        if ($loopStmt instanceof While_) {
            return $loopStmt->cond;
        } elseif ($loopStmt instanceof For_) {
            return $loopStmt->cond[0] ?? null;
        } elseif ($loopStmt instanceof Foreach_) {
            return $loopStmt->expr;
        }
        return null;
    }

    private function pathEndsWithTermination(array $stmts): bool
    {
        if (empty($stmts)) {
            return false;
        }
        
        $lastStmt = end($stmts);
        return $lastStmt instanceof Return_ || 
               $lastStmt instanceof Throw_ ||
               $lastStmt instanceof Continue_ ||
               $lastStmt instanceof Break_;
    }
    
}
