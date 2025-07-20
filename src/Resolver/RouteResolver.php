<?php

namespace Apilyser\Resolver;

use Apilyser\Parser\Route;
use Apilyser\Parser\Route\RouteFunctionParser;
use PhpParser\Node\Stmt\Class_;

class RouteResolver
{

    /**
     * @param RouteFunctionParser[] $routeParsers
     * @param RouteStrategy[] $strategies
     */
    public function __construct(
        private array $routeParsers,
        private array $strategies
    ) {}

    public function resolve(Class_ $class): ?RouteFunctionParser
    {
        foreach ($this->routeParsers as $routeParser) {
            if ($routeParser->hasRoute($class->attrGroups)) {
                return $routeParser;
            }
        }

        return null;
    }

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