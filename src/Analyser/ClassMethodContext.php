<?php

namespace Apilyser\Analyser;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

class ClassMethodContext
{

    public function __construct(
        public array $imports,
        public Class_ $class,
        public ClassMethod $method
    ) {}
}