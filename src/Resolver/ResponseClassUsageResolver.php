<?php

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
     * @param Node $node
     * 
     * @return ?ResponseCall
     */
    public function resolve(ClassMethodContext $context, Node $node, ?ResponseCall $modifierResponseCall = null): ?ResponseCall
    {
        foreach ($this->classUsageResolvers as $resolver) {
            if ($resolver->canResolve($node)) {
                return $resolver->resolve($context, $node, $modifierResponseCall);
            }
        }

        return null;
    }
}