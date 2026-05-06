<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\ResponseDefinition;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Util\Logger;

class ResponseAnalyser
{
    public function __construct(
        private Logger $logger,
        private MethodAnalyser $methodAnalyser
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseDefinition[]
     */
    public function analyse(ClassMethodContext $context): array
    {
        $this->logger->log("Analysing responses");
        
        $responseCalls = $this->methodAnalyser->analyse($context);

        $result = array_map(
            fn(ResponseCall $responseCall) => $this->mapResponseCallToResponseDefinition($responseCall),
            $responseCalls
        );

        $res = array_unique($result);

        return $res;
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
            statusCode: $call->statusCode ?? 200
        );
    }

}