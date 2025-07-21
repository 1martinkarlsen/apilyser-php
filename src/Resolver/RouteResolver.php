<?php

namespace Apilyser\Resolver;

use Apilyser\Parser\Route;
use Apilyser\Parser\Route\RouteStrategy;

class RouteResolver
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
    public function resolveStrategy(string $path): array
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