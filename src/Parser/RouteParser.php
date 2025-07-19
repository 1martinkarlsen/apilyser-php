<?php

namespace Apilyser\Parser;

use Apilyser\Parser\Route;
use Apilyser\Resolver\RouteResolver;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

class RouteParser
{

    public function __construct(
        private string $projectPath,
        private RouteResolver $routeResolver
    ) {}

    /**
     * @return Route[]
     */
    public function parse(): array
    {
        return $this->routeResolver->resolveStrategy($this->projectPath);
    }

    public function parseFullRoute(Class_ $class, ClassMethod $method): ?Route
    {
        $routeParser = $this->routeResolver->resolve($class);

        if ($routeParser == null) {
            return null;
        }

        return $routeParser->parse($class, $method);
    }
}