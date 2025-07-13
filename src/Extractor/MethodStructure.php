<?php

namespace Apilyser\Extractor;

use PhpParser\Node\Stmt\ClassMethod;

class MethodStructure
{

    /**
     * @var ClassMethod $method
     * @var MethodScope[] $scopes
     */
    public function __construct(
        public ClassMethod $method,
        public array $scopes
    ) {}
}