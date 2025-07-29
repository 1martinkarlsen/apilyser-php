<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use PhpParser\Node\Stmt\ClassMethod;

class MethodStructure
{

    /**
     * @param ClassMethod $method
     * @param MethodScope[] $scopes
     */
    public function __construct(
        public ClassMethod $method,
        public array $scopes
    ) {}
}