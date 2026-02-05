<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Parser\Route;
use Apilyser\Parser\Route\RouteStrategy;

class RouteCollector
{

    /**
     * @param RouteStrategy[] $strategies
     */
    public function __construct(
        private array $strategies
    ) {}

    /**
     * @return Route[]
     */
    public function resolveRoutes(string $path): array
    {
        $routes = [];

        foreach ($this->strategies as $strategy) {
            if ($strategy->canHandle($path)) {
                array_push(
                    $routes,
                    ...$strategy->parseRoutes($path)
                );
            }
        }

        return $routes;
    }
}
