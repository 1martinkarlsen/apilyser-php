<?php declare(strict_types=1);

namespace Apilyser\Ast;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

class ClassFinder
{

    public function __construct(
        private NodeFinder $nodeFinder
    ) {}

    public function extract(array $stmts): array
    {
        return $this->nodeFinder->findInstanceOf($stmts, Class_::class);
    }
}
