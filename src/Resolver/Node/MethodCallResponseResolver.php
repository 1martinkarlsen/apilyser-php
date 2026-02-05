<?php declare(strict_types=1);

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Framework\FrameworkRegistry;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;

class MethodCallResponseResolver implements ResponseNodeResolver
{

    public function __construct(
        private FrameworkRegistry $frameworkRegistry
    ) {}

    public function canResolve(Node $node): bool
    {
        return $node instanceof MethodCall;
    }

    public function resolve(
        ClassMethodContext $context,
        array $methodJourney,
        Node $node,
        ?ResponseCall $modifierResponseCall = null
    ): ?ResponseCall {
        foreach ($this->frameworkRegistry->getParsers() as $http) {
            $responseDef = $http->tryParseCallLikeAsResponse($context, $node, $methodJourney, $modifierResponseCall);
            if ($responseDef != null) {
                return $responseDef;
            }
        }

        return null;
    }
}
