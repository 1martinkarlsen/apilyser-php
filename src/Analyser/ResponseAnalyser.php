<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\ResponseDefinition;
use Apilyser\Resolver\ApiFrameworkResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseAnalyser
{

    public function __construct(
        private OutputInterface $output,
        private ApiFrameworkResolver $apiFrameworkResolver,
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
        $usedResponseClasses = $this->apiFrameworkResolver->resolve($context->method, $context->imports);
        $results = $this->responseResolver->resolve($context, $usedResponseClasses);

        return array_map(
            function(ResponseCall $responseCall) {
                $res = $this->mapResponseCallToResponseDefinition($responseCall);
                $this->output->writeln("Response: " . $res->toString());
                return $res;
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