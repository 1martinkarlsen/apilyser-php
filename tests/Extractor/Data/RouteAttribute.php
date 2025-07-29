<?php declare(strict_types=1);

use Attribute;

#[Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route
{

    private ?string $path;
    private ?string $name;

    public function __construct(
        string|array|null $path = null,
        ?string $name = null,
    ) {
        $this->path = $path;
        $this->name = $name;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}