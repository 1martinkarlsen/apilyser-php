<?php declare(strict_types=1);

namespace Apilyser\Ast\Visitor;

use Apilyser\Resolver\NamespaceResolver;

class ClassUsageVisitorFactory
{

    public function __construct(private NamespaceResolver $namespaceResolver) {}

    public function create(string $className, array $imports)
    {
        return new ClassUsageVisitor(
            namespaceResolver: $this->namespaceResolver,
            className: $className,
            imports: $imports
        );
    }
}
