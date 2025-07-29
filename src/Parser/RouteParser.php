<?php declare(strict_types=1);

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
}