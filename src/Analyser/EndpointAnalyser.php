<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\EndpointDefinition;
use Apilyser\Parser\Route;
use PhpParser\NodeDumper;

/**
 * Responsible for parsing endpoints from code.
 */
class EndpointAnalyser
{

    public function __construct(
        private RequestAnalyser $requestAnalyzer,
        private ResponseAnalyser $responseAnalyzer
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ?EndpointDefinition
     */
    public function analyse(Route $route, ClassMethodContext $context): ?EndpointDefinition
    {
        echo "Path " . $route->path . "\n";
        $requests = $this->requestAnalyzer->analyse($context);
        $responses = $this->responseAnalyzer->analyse($context);

        foreach ($responses as $res) {
            echo "Res: " . $res->toString() . "\n";
        }

        return new EndpointDefinition(
            $route->path,
            $route->method,
            $requests,
            $responses
        );
    }
}