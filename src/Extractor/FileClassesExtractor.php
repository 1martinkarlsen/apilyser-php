<?php

namespace Apilyser\Extractor;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

class FileClassesExtractor
{

    public function __construct(
        private NodeFinder $nodeFinder
    ) {}

    public function extract(array $stmts): array
    {
        return $this->nodeFinder->findInstanceOf($stmts, Class_::class);
    }
}