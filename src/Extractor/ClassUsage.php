<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use PhpParser\Node;

class ClassUsage
{
    public function __construct(
        public string $className, // Full namespaced class name
        public string $usageType,
        public Node $node,
        public ?Node $parent
    ) {}
}