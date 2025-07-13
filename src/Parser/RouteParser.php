<?php

namespace Apilyser\Parser;

use Apilyser\Parser\Route;
use Apilyser\Resolver\RouteResolver;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

class RouteParser
{

    public function __construct(
        private RouteResolver $routeResolver
    ) {}

    public function parseFullRoute(Class_ $class, ClassMethod $method): ?Route
    {
        $routeParser = $this->routeResolver->resolve($class);

        if ($routeParser == null) {
            return null;
        }

        return $routeParser->parse($class, $method);
    }
}