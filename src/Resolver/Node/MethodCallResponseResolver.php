<?php declare(strict_types=1);

namespace Apilyser\Resolver\Node;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Framework\FrameworkRegistry;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Util\Logger;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;

class MethodCallResponseResolver implements ResponseNodeResolver
{

    public function __construct(
        private Logger $logger,
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
        $this->logger->info("Method call response resolver");
        foreach ($this->frameworkRegistry->getAdapters() as $http) {
            $responseDef = $http->tryParseCallLikeAsResponse($context, $node, $methodJourney, $modifierResponseCall);
            if ($responseDef != null) {
                return $responseDef;
            }
        }

        return null;
    }
}
