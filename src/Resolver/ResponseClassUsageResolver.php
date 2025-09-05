<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Resolver\Node\ResponseNodeResolver;
use PhpParser\Node;

class ResponseClassUsageResolver
{

    /**
     * @param ResponseNodeResolver[] $classUsageResolvers
     */
    public function __construct(
        private array $classUsageResolvers
    ) {}

    /**
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param Node $node
     * 
     * @return ?ResponseCall
     */
    public function resolve(ClassMethodContext $context, array $methodJourney, Node $node, ?ResponseCall $modifierResponseCall = null): ?ResponseCall
    {
        foreach ($this->classUsageResolvers as $resolver) {
            if ($resolver->canResolve($node)) {
                return $resolver->resolve($context, $methodJourney, $node, $modifierResponseCall);
            }
        }

        return null;
    }
}