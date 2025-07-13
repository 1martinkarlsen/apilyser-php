<?php

namespace Apilyser\Parser\Route;

use Apilyser\Parser\Route;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

interface RouteFunctionParser {

    public function hasRoute(array $attrGroups): bool;

    public function parse(Class_ $class, ClassMethod $method): ?Route;
}
