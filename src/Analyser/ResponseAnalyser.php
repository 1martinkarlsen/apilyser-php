<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\ResponseDefinition;
use Apilyser\Extractor\ClassExtractor;
use Apilyser\Extractor\MethodStructureExtractor;
use Apilyser\Extractor\VariableUsageExtractor;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;
use Exception;
use PhpParser\NodeDumper;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseAnalyser
{

    public function __construct(
        private OutputInterface $output,
        private ClassExtractor $classExtractor,
        private MethodStructureExtractor $methodStructureExtractor,

        private VariableUsageExtractor $variableUsageExtractor,
        private ResponseResolver $responseResolver,
        private NodeDumper $dumper
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseDefinition[]
     */
    public function analyse(ClassMethodContext $context): array
    {
        // Find used classes in method that exist in api parser
        $scopedMethod = $this->methodStructureExtractor->extract($context->method);
        $usedResponseClasses = $this->classExtractor->extract($scopedMethod->method, $context->imports);

        $results = $this->responseResolver->resolve($context, $usedResponseClasses);

        return array_map(
            function(ResponseCall $responseCall) {
                return $this->mapResponseCallToResponseDefinition($responseCall);
            },
            $results
        );
    }

    /**
     * @param ResponseCall $call
     * 
     * @return ResponseDefinition
     */
    private function mapResponseCallToResponseDefinition(ResponseCall $call): ResponseDefinition
    {
        return new ResponseDefinition(
            type: $call->type,
            structure: $call->structure,
            statusCode: $call->statusCode
        );
    }

}