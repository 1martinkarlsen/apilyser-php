<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Extractor\ClassUsage;
use Apilyser\Extractor\VariableUsageExtractor;

class ResponseResolver
{

    /**
     * @param VariableUsageExtractor $variableUsageExtractor
     * @param ResponseClassUsageResolver $classUsageResolver
     */
    public function __construct(
        private VariableUsageExtractor $variableUsageExtractor,
        private ResponseClassUsageResolver $classUsageResolver
    ) {}

    /**
     * Resolving how to handle used classes
     * 
     * @param ClassMethodContext $context
     * @param ClassUsage[] $usedClasses
     * 
     * @return ResponseCall[]
     */
    public function resolve(ClassMethodContext $context, array $usedClasses): array
    {
        $results = []; 
        foreach ($usedClasses as $usedClass) {
            // Here we will look through each class.
            // We need to find all usages of $usedClass to define the response.
            $result = null;

            // Class assigned (ex: $var = new Class())
            $result = $this->classUsageResolver->resolve($context, $usedClass->node);
            
            if ($result != null) {
                $results[] = $result;
            }
        }

        return $results;
    }
}