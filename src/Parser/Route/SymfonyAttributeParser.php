<?php

namespace Apilyser\Parser\Route;

use Apilyser\Extractor\AttributeExtractor;
use Apilyser\Parser\Route;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Symfony\Component\Console\Output\OutputInterface;

class SymfonyAttributeParser implements RouteFunctionParser
{

    private const ATTR_ROUTE_NAME = "Route";
    private const ATTR_PATH_NAME = "path";
    private const ATTR_METHOD_NAME = "methods";

    public function __construct(
        private OutputInterface $output,
        private AttributeExtractor $extractor
    ) {}

    public function hasRoute(array $attrGroups): bool
    {
        return $this->extractor->extract($attrGroups, self::ATTR_ROUTE_NAME) != null;
    }

    public function parse(Class_ $class, ClassMethod $method): ?Route
    {
        $classAttr = $this->extractor->extract($class->attrGroups, self::ATTR_ROUTE_NAME);
        $functionAttr = $this->extractor->extract($method->attrGroups, self::ATTR_ROUTE_NAME);

        $classRoute = $this->findRoutePath($classAttr);
        $functionRoute = $this->findRoutePath($functionAttr);
        $functionMethod = $this->findRouteMethod($functionAttr);

        if ($functionMethod == null) {
            return null;
        }

        $routePath = $classRoute != null 
            ? ($classRoute . $functionRoute) 
            : $functionRoute;

        return new Route(
            path: $routePath,
            method: strtoupper($functionMethod)
        );
    }

    private function findRoutePath(Attribute $attr): ?string
    {   
        foreach ($attr->args as $arg) {
            if ($arg->name == self::ATTR_PATH_NAME) {
                if ($arg->value instanceof String_) {
                    return $arg->value->value;
                }
            }
        }

        return null;
    }

    private function findRouteMethod(Attribute $attr): ?string
    {
        foreach ($attr->args as $arg) {
            if ($arg->name == self::ATTR_METHOD_NAME) {
                if ($arg->value instanceof Array_) {
                    $firstMethod = $arg->value->items[0];
                    if ($firstMethod->value instanceof String_) {
                        return $firstMethod->value->value;
                    }
                }
            }
        }

        return null;
    }
}