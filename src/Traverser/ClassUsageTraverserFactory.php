<?php declare(strict_types=1);

namespace Apilyser\Traverser;

use Apilyser\Parser\Api\ApiParser;
use Apilyser\Resolver\NamespaceResolver;

class ClassUsageTraverserFactory
{

    public function __construct(
        private NamespaceResolver $namespaceResolver
    ) {}

    public function create(ApiParser $apiParser, array $imports)
    {
        return new ClassUsageTraverser(
            apiParser: $apiParser,
            namespaceResolver: $this->namespaceResolver,
            imports: $imports
        );
    }
}