<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\EndpointDefinition;
use Apilyser\Parser\Route;

/**
 * Responsible for parsing endpoints from code.
 */
class EndpointAnalyser
{

    public function __construct(
        private RequestAnalyser $requestAnalyser,
        private ResponseAnalyser $responseAnalyser
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ?EndpointDefinition
     */
    public function analyse(Route $route, ClassMethodContext $context): ?EndpointDefinition
    {
        $requests = $this->requestAnalyser->analyse($context);
        $responses = $this->responseAnalyser->analyse($context);

        return new EndpointDefinition(
            $route->path,
            $route->method,
            $requests,
            $responses
        );
    }
}