<?php declare(strict_types=1);

namespace Apilyser\Traverser;

use Apilyser\Resolver\NamespaceResolver;

class ClassUsageTraverserFactory
{

    public function __construct(
        private NamespaceResolver $namespaceResolver
    ) {}

    public function create(string $className, array $imports)
    {
        $fullClassName = $this->namespaceResolver->findFullNamespaceForClass($className, $imports);

        return new ClassUsageTraverser(
            className: $fullClassName
        );
    }
}