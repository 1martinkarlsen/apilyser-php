<?php

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;

interface ResponseNodeResolver
{
    public function canResolve(Node $node): bool;
    public function resolve(ClassMethodContext $context, Node $node, ?ResponseCall $modifierResponseCall = null): ?ResponseCall;
}