<?php declare(strict_types=1);

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;

interface ResponseNodeResolver
{
    public function canResolve(Node $node): bool;
    public function resolve(
        ClassMethodContext $context,
        array $methodJourney,
        Node $node, 
        ?ResponseCall $modifierResponseCall = null
    ): ?ResponseCall;
}