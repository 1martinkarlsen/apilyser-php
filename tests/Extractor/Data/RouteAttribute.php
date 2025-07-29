<?php declare(strict_types=1);

use Attribute;

#[Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route
{

    public function __construct(
        string|array|null $path = null,
        private ?string $name = null,
    ) {}
    
}