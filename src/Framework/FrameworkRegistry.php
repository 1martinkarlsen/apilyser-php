<?php declare(strict_types=1);

namespace Apilyser\Framework;

class FrameworkRegistry
{
    /**
     * @var FrameworkAdapter[]
     */
    private array $adapters = [];

    public function registerAdapter(FrameworkAdapter $parser)
    {
        array_push(
            $this->adapters,
            $parser
        );
    }

    /**
     * @return FrameworkAdapter[]
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    public function getRequestAdapter(string $fullNamespace): ?FrameworkAdapter
    {
        foreach ($this->adapters as $parser) {
            if ($parser->supportRequestClass($fullNamespace)) {
                return $parser;
            }
        }

        return null;
    }
}
