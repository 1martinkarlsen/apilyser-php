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

    /**
     * @param ClassMethod $method
     *
     * @return \Generator<MethodPathDefinition>
     */
    public function extract(ClassMethod $method): \Generator
    {
        yield from $this->extractPaths($method->stmts, new MethodPathDefinition());
    }

    /**
     * @param Node[] $stmts
     * @param MethodPathDefinition $currentPath
     *
     * @return \Generator<MethodPathDefinition>
     */
    private function extractPaths(array $stmts, MethodPathDefinition $currentPath): \Generator
    {
        foreach ($stmts as $index => $statement) {
            $currentPath->addStatement($statement);

            switch (true) {
                case $statement instanceof If_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    yield from $this->handleConditional($statement, $currentPath, $remainingStmts);
                    return;

                case $statement instanceof Return_:
                    yield $currentPath;
                    return;

                case $statement instanceof Throw_:
                    yield $currentPath;
                    return;

                case $statement instanceof While_:
                case $statement instanceof For_:
                case $statement instanceof Foreach_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    yield from $this->handleLoop($statement, $currentPath, $remainingStmts);
                    return;

                case $statement instanceof Switch_:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    yield from $this->handleSwitch($statement, $currentPath, $remainingStmts);
                    return;

                case $statement instanceof TryCatch:
                    $remainingStmts = array_slice($stmts, $index + 1);
                    yield from $this->handleTryCatch($statement, $currentPath, $remainingStmts);
                    return;
            }
        }

        yield $currentPath;
    }

    private function handleConditional(Node\Stmt\If_ $ifStmt, MethodPathDefinition $basePath, array $remainingStmts): \Generator
    {
        $truePath = clone $basePath;
        $truePath->addCondition("if", $ifStmt->cond, true);

        if ($this->pathEndsWithTermination($ifStmt->stmts)) {
            yield from $this->extractPaths($ifStmt->stmts, $truePath);
        } else {
            yield from $this->extractPaths(array_merge($ifStmt->stmts, $remainingStmts), $truePath);
        }

        foreach ($ifStmt->elseifs as $elseif) {
            $elseifPath = clone $basePath;
            $elseifPath->addCondition("elseif", $elseif->cond, true);

            if ($this->pathEndsWithTermination($elseif->stmts)) {
                yield from $this->extractPaths($elseif->stmts, $elseifPath);
            } else {
                yield from $this->extractPaths(array_merge($elseif->stmts, $remainingStmts), $elseifPath);
            }
        }

        if ($ifStmt->else) {
            $elsePath = clone $basePath;
            $elsePath->addCondition("else", $ifStmt->cond, false);

            if ($this->pathEndsWithTermination($ifStmt->else->stmts)) {
                yield from $this->extractPaths($ifStmt->else->stmts, $elsePath);
            } else {
                yield from $this->extractPaths(array_merge($ifStmt->else->stmts, $remainingStmts), $elsePath);
            }
        } else {
            $elsePath = clone $basePath;
            $elsePath->addCondition("implicit-else", $ifStmt->cond, false);
            yield from $this->extractPaths($remainingStmts, $elsePath);
        }
    }

    /**
     * @param While_|For_|Foreach_ $loopStmt
     */
    private function handleLoop(Node $loopStmt, MethodPathDefinition $basePath, array $remainingStmts): \Generator
    {
        $loopPath = clone $basePath;
        $loopPath->addCondition("loop-enter", $this->getLoopCondition($loopStmt), true);

        $loopBodyStmts = $loopStmt->stmts;

        if ($this->pathEndsWithTermination($loopBodyStmts)) {
            yield from $this->extractPaths($loopBodyStmts, $loopPath);
        } else {
            yield from $this->extractPaths(array_merge($loopBodyStmts, $remainingStmts), $loopPath);
        }

        $skipPath = clone $basePath;
        $skipPath->addCondition("loop-skip", $this->getLoopCondition($loopStmt), false);
        yield from $this->extractPaths($remainingStmts, $skipPath);
    }

    private function handleSwitch(Node\Stmt\Switch_ $switchStmt, MethodPathDefinition $basePath, array $remainingStmts): \Generator
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

            if ($this->pathEndsWithTermination($case->stmts)) {
                yield from $this->extractPaths($case->stmts, $casePath);
            } else {
                yield from $this->extractPaths(array_merge($case->stmts, $remainingStmts), $casePath);
            }
        }

        if (!$hasDefaultCase) {
            $noMatchPath = clone $basePath;
            $noMatchPath->addCondition("no-case-match", null, false);
            yield from $this->extractPaths($remainingStmts, $noMatchPath);
        }
    }

    private function handleTryCatch(Node\Stmt\TryCatch $tryCatch, MethodPathDefinition $basePath, array $remainingStmts): \Generator
    {
        if ($this->pathEndsWithTermination($tryCatch->stmts)) {
            yield from $this->extractPaths($tryCatch->stmts, $basePath);
        } else {
            yield from $this->extractPaths(array_merge($tryCatch->stmts, $remainingStmts), $basePath);
        }

        foreach ($tryCatch->catches as $catch) {
            $catchPath = clone $basePath;
            if ($this->pathEndsWithTermination($catch->stmts)) {
                yield from $this->extractPaths($catch->stmts, $catchPath);
            } else {
                yield from $this->extractPaths(array_merge($catch->stmts, $remainingStmts), $catchPath);
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
