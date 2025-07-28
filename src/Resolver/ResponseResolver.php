<?php

namespace Apilyser\Resolver;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Extractor\ClassUsage;
use Apilyser\Extractor\VariableUsageExtractor;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseResolver
{

    /**
     * @param OutputInterface $output
     * @param VariableUsageExtractor $variableUsageExtractor
     * @param ResponseClassUsageResolver $classUsageResolver
     */
    public function __construct(
        private OutputInterface $output,
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
            if ($usedClass->parent instanceof Assign) {
                $result = $this->collectFromAssignedResponseCall($usedClass->parent, $context);
            } else {
                $result = $this->classUsageResolver->resolve($context, $usedClass->node);
            }
            
            if ($result != null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function collectFromAssignedResponseCall(Assign $assignedNode, ClassMethodContext $context): ?ResponseCall
    {
        $result = null;
        $calls = [];
        if ($assignedNode->var instanceof Variable) {
            $calls = $this->variableUsageExtractor->extractVariableUsage(
                node: $assignedNode->var,
                method: $context->method
            );
        }

        foreach ($calls as $call) {
            // NodeHandler
            $responseCall = $this->classUsageResolver->resolve($context, $call, $result);
            if ($responseCall != null && $responseCall !== $result) {
                $result = $responseCall;
            }
        }

        return $result;
    }
}