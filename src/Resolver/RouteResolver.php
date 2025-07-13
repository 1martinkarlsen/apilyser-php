<?php

namespace Apilyser\Resolver;

use PhpParser\Node\Stmt\Class_;

class RouteResolver
{

    /**
     * @param RouteFunctionParser[] $routeParsers
     */
    public function __construct(
        private array $routeParsers
    ) {}

    public function resolve(Class_ $class)
    {
        foreach ($this->routeParsers as $routeParser) {
            if ($routeParser->hasRoute($class->attrGroups)) {
                return $routeParser;
            }
        }
    }
}