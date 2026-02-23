<?php

namespace Apilyser\Ast;

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
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

class ExecutionPathFinder
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
        $this->extractPaths($method->stmts, new MethodPathDefinition());

        return $this->paths;
    }

    /**
     * @param Node[] $stmts
     * @param MethodPathDefinition $currentPath
     */
    private function extractPaths(array $stmts, MethodPathDefinition $currentPath): void
    {
        foreach ($stmts as $index => $statement) {
            $newPath = clone $currentPath;
            $newPath->addStatement($statement);

            switch (true) {
                case $statement instanceof If_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    $this->handleConditional($statement, $newPath, $remainingStmts);
                    return;

                case $statement instanceof Return_:
                    $this->paths[] = $newPath;
                    return;

                case $statement instanceof Throw_:
                    $this->paths[] = $newPath;
                    return;

                case $statement instanceof While_:
                case $statement instanceof For_:
                case $statement instanceof Foreach_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    $this->handleLoop($statement, $newPath, $remainingStmts);
                    return;

                case $statement instanceof Switch_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    $this->handleSwitch($statement, $newPath, $remainingStmts);
                    return;

                case $statement instanceof TryCatch:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    $this->handleTryCatch($statement, $newPath, $remainingStmts);
                    return;

                default:
                    $currentPath = $newPath;
            }
        }

        $this->paths[] = $currentPath;
    }

    private function handleConditional(Node\Stmt\If_ $ifStmt, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        // True branch
        $truePath = clone $basePath;
        $truePath->addCondition("if", $ifStmt->cond, true);

        // Continue with remaining statements after if block (if no return/throw)
        if ($this->pathEndsWithTermination($ifStmt->stmts)) {
            $this->extractPaths($ifStmt->stmts, $truePath);
        } else {
            $mergedStmts = array_merge($ifStmt->stmts, $remainingStmts);
            $this->extractPaths($mergedStmts, $truePath);
        }

        // Handle elseif chains
        foreach ($ifStmt->elseifs as $elseif) {
            $elseifPath = clone $basePath;
            $elseifPath->addCondition("elseif", $elseif->cond, true);

            // Continue with remaining statements after elseif block
            if ($this->pathEndsWithTermination($elseif->stmts)) {
                $this->extractPaths($elseif->stmts, $elseifPath);
            } else {
                $mergedStmts = array_merge($elseif->stmts, $remainingStmts);
                $this->extractPaths($mergedStmts, $elseifPath);
            }
        }

        // Else branch (or implicit else if no else block)
        if ($ifStmt->else) {
            $elsePath = clone $basePath;
            $elsePath->addCondition("else", $ifStmt->cond, false);

            // Continue with remaining statements after else block
            if ($this->pathEndsWithTermination($ifStmt->else->stmts)) {
                $this->extractPaths($ifStmt->else->stmts, $elsePath);
            } else {
                $mergedStmts = array_merge($ifStmt->else->stmts, $remainingStmts);
                $this->extractPaths($mergedStmts, $elsePath);
            }
        } else {
            // Implicit else path (condition was false, continue after if)
            $elsePath = clone $basePath;
            $elsePath->addCondition("implicit-else", $ifStmt->cond, false);
            $this->extractPaths($remainingStmts, $elsePath);
        }
    }

    /**
     * @param While_|For_|Foreach_ $loopStmt
     * @param MethodPathDefinition $basePath
     * @param array $remainingStmts
     */
    private function handleLoop(Node $loopStmt, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        // Path that enters the loop
        $loopPath = clone $basePath;
        $loopPath->addCondition("loop-enter", $this->getLoopCondition($loopStmt), true);

        $loopBodyStmts = [];
        if ($loopStmt instanceof Node\Stmt\While_) {
            $loopBodyStmts = $loopStmt->stmts;
        } elseif ($loopStmt instanceof Node\Stmt\For_) {
            $loopBodyStmts = $loopStmt->stmts;
        } elseif ($loopStmt instanceof Node\Stmt\Foreach_) {
            $loopBodyStmts = $loopStmt->stmts;
        }

        // Continue with remaining statements after loop (if no break/return)
        if ($this->pathEndsWithTermination($loopBodyStmts)) {
            $this->extractPaths($loopBodyStmts, $loopPath);
        } else {
            $mergedStmts = array_merge($loopBodyStmts, $remainingStmts);
            $this->extractPaths($mergedStmts, $loopPath);
        }

        // Path that skips the loop
        $skipPath = clone $basePath;
        $skipPath->addCondition("loop-skip", $this->getLoopCondition($loopStmt), false);
        $this->extractPaths($remainingStmts, $skipPath);
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

            // Continue with remaining statements after switch (if no break/return)
            if ($this->pathEndsWithTermination($case->stmts)) {
                $this->extractPaths($case->stmts, $casePath);
            } else {
                $mergedStmts = array_merge($case->stmts, $remainingStmts);
                $this->extractPaths($mergedStmts, $casePath);
            }
        }

        // If no default case, create a path that doesn't match any case
        if (!$hasDefaultCase) {
            $noMatchPath = clone $basePath;
            $noMatchPath->addCondition("no-case-match", null, false);
            $this->extractPaths($remainingStmts, $noMatchPath);
        }
    }

    private function handleTryCatch(Node\Stmt\TryCatch $tryCatch, MethodPathDefinition $basePath, array $remainingStmts): void
    {
        // Try body paths (merged with statements after the try-catch if the body does not terminate)
        if ($this->pathEndsWithTermination($tryCatch->stmts)) {
            $this->extractPaths($tryCatch->stmts, $basePath);
        } else {
            $mergedTryStmts = array_merge($tryCatch->stmts, $remainingStmts);
            $this->extractPaths($mergedTryStmts, $basePath);
        }

        // Each catch block creates its own path
        foreach ($tryCatch->catches as $catch) {
            $catchPath = clone $basePath;
            if ($this->pathEndsWithTermination($catch->stmts)) {
                $this->extractPaths($catch->stmts, $catchPath);
            } else {
                $mergedCatchStmts = array_merge($catch->stmts, $remainingStmts);
                $this->extractPaths($mergedCatchStmts, $catchPath);
            }
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
