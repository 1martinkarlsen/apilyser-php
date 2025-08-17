<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\ResponseDefinition;
use Apilyser\Extractor\ClassExtractor;
use Apilyser\Extractor\MethodStructureExtractor;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;

class ResponseAnalyser
{

    public function __construct(
        private ClassExtractor $classExtractor,
        private MethodStructureExtractor $methodStructureExtractor,
        private ResponseResolver $responseResolver
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseDefinition[]
     */
    public function analyse(ClassMethodContext $context): array
    {
        // Find used classes in method that exist in api parser
        $usedResponseClasses = $this->classExtractor->extract($context->method, $context->imports);
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