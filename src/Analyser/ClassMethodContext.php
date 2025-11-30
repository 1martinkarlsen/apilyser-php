<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;

class ClassMethodContext
{

    public function __construct(
        public Namespace_ $namespace,
        public array $imports,
        public Class_ $class,
        public ClassMethod $method
    ) {}
}