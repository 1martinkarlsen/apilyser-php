<?php

namespace Apilyser\Resolver;

use Apilyser\Parser\Route\RouteFunctionParser;
use PhpParser\Node\Stmt\Class_;
use Symfony\Component\Console\Output\OutputInterface;

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
    public function resolveStrategy(string $rootPath): array
    {
        $routes = [];

        foreach ($this->strategies as $strategy) {
            if ($strategy->canHandle($rootPath)) {
                array_push(
                    $routes, 
                    ...$strategy->parseRoutes($rootPath)
                );
            }
        }

        return $routes;
    }
}