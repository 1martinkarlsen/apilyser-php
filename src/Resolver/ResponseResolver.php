<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Extractor\ClassUsage;

class ResponseResolver
{

    public function __construct(
        private ResponseClassUsageResolver $classUsageResolver,
        private TypeStructureResolver $typeStructureResolver
    ) {}

    /**
     * Resolving how to handle used classes
     * 
     * @param ClassMethodContext $context
     * @param Node[] $methodJourney
     * @param ClassUsage[] $usedClasses
     * 
     * @return ResponseCall[]
     */
    public function resolveUsedClasses(ClassMethodContext $context, array $methodJourney, array $usedClasses): array
    {
        $results = []; 
        foreach ($usedClasses as $usedClass) {
            // Here we will look through each class.
            // We need to find all usages of $usedClass to define the response.
            $result = null;

            // Class assigned (ex: $var = new Class())
            $result = $this->classUsageResolver->resolve($context, $methodJourney, $usedClass->node);
            
            if ($result != null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @return ResponseCall[]
     */
    public function resolveReturns(ClassMethodContext $context, array $methodJourney, array $returns): array
    {
        $results = [];

        foreach ($returns as $return) {
            $result = $this->typeStructureResolver->resolveFromExpression($context, $methodJourney, $return->expr);

            if ($result != null) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
