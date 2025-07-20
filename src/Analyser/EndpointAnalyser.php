<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\EndpointDefinition;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route;
use Apilyser\Parser\RouteParser;
use Apilyser\Resolver\NamespaceResolver;
use PhpParser\NodeFinder;
use Symfony\Component\Console\Output\OutputInterface;

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
        $requests = $this->requestAnalyzer->analyse($context);
        $responses = $this->responseAnalyzer->analyse($context);

        return new EndpointDefinition(
            $route->path,
            $route->method,
            $requests,
            $responses
        );
    }
}