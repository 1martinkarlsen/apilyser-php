<?php

namespace Apilyser\Ast;

use Apilyser\Framework\FrameworkAdapter;
use Apilyser\Framework\FrameworkRegistry;

class FrameworkClassFinder
{

    public function __construct(
        private ClassUsageFinder $classUsageFinder,
        private FrameworkRegistry $frameworkRegistry
    ) {}

    /**
     * @param array $stmts
     * @param array $imports
     *
     * @return ClassUsage[]
     */
    public function find(array $stmts, array $imports): array
    {
        /** @var ClassUsage[] */
        $result = [];

        foreach ($this->frameworkRegistry->getParsers() as $http) {
            $responseClasses = $this->extractUsedClasses(
                stmts: $stmts,
                imports: $imports,
                frameworkAdapter: $http
            );

            array_push(
                $result,
                ...$responseClasses
            );
        }

        return $result;
    }

    /**
     * @return ClassUsage[]
     */
    private function extractUsedClasses(array $stmts, array $imports, FrameworkAdapter $frameworkAdapter): array
    {
        /** @var ClassUsage[] */
        $result = [];
        $responseClasses = $frameworkAdapter->getSupportedResponseClasses();

        foreach ($responseClasses as $responseClass) {
            $usages = $this->classUsageFinder->extract(
                stmts: $stmts,
                className: $responseClass,
                imports: $imports
            );

            array_push(
                $result,
                ...$usages
            );
        }

        return $result;
    }
}
