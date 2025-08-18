<?php declare(strict_types=1);

namespace Apilyser\Traverser;

use Apilyser\Resolver\NamespaceResolver;
use Symfony\Component\Console\Output\OutputInterface;

class ClassUsageTraverserFactory
{

    public function __construct(private NamespaceResolver $namespaceResolver) 
    {
    }

    public function create(string $className, array $imports)
    {
        return new ClassUsageTraverser(
            namespaceResolver: $this->namespaceResolver,
            className: $className,
            imports: $imports
        );
    }
}