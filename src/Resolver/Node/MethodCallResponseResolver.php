<?php

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Symfony\Component\Console\Output\OutputInterface;

class MethodCallResponseResolver implements ResponseNodeResolver
{

    public function __construct(
        private OutputInterface $output,
        private HttpDelegate $httpDelegate
    ) {}

    public function canResolve(Node $node): bool
    {
        return $node instanceof MethodCall;
    }

    public function resolve(ClassMethodContext $context, Node $node, ?ResponseCall $modifierResponseCall = null): ?ResponseCall
    {
        foreach ($this->httpDelegate->getParsers() as $http) {
            $responseDef = $http->tryParseCallLikeAsResponse($context, $node, $modifierResponseCall);
            if ($responseDef != null) {
                return $responseDef;
            }
        }

        return null;
    }
}