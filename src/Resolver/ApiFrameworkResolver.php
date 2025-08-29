<?php

namespace Apilyser\Resolver;

use Apilyser\Extractor\ClassExtractor;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\ClassUsage;

class ApiFrameworkResolver
{

    public function __construct(
        private ClassExtractor $classExtractor,
        private HttpDelegate $httpDelegate
    ) {}

    /**
     * @return ClassUsage[]
     */
    public function resolve(array $stmts, array $imports): array
    {
        /** @var ClassUsage[] */
        $result = [];

        foreach ($this->httpDelegate->getParsers() as $http) {
            $responseClasses = $this->extractUsedClasses(
                stmts: $stmts,
                imports: $imports,
                apiParser: $http
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
    private function extractUsedClasses(array $stmts, array $imports, ApiParser $apiParser): array
    {
        /** @var ClassUsage[] */
        $result = [];
        $responseClasses = $apiParser->getSupportedResponseClasses();

        foreach ($responseClasses as $responseClass) {
            $usages = $this->classExtractor->extract(
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