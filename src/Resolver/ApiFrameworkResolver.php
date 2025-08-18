<?php

namespace Apilyser\Resolver;

use Apilyser\Extractor\ClassExtractor;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use PhpParser\Node\Stmt\ClassMethod;

class ApiFrameworkResolver
{

    public function __construct(
        private ClassExtractor $classExtractor,
        private HttpDelegate $httpDelegate
    ) {}

    public function resolve(ClassMethod $method, array $imports): array
    {
        $result = [];

        foreach ($this->httpDelegate->getParsers() as $http) {
            $responseClasses = $this->extractUsedClasses(
                method: $method,
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

    private function extractUsedClasses(ClassMethod $method, array $imports, ApiParser $apiParser): array
    {
        $result = [];
        $responseClasses = $apiParser->getSupportedResponseClasses();

        foreach ($responseClasses as $responseClass) {
            $usages = $this->classExtractor->extract(
                method: $method,
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