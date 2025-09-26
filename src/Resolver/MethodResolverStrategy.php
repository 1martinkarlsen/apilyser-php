<?php

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;

interface MethodResolverStrategy
{
    public function resolveMethod(ClassMethodContext $context): array;
}