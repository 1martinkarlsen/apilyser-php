<?php

namespace Apilyser\Analyser;

use Apilyser\Comparison\ApiComparison;
use Apilyser\Resolver\RouteResolver;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

class Analyser
{

    public function __construct(
        private OutputInterface $output,
        private OpenApiAnalyser $openApiAnalyser,
        private RouteResolver $routeResolver,
        private FileAnalyser $fileAnalyser,
        private ApiComparison $comparison
    ) {}

    /**
     * @param string $folderPath
     *
     * @return EndpointResult[]
     */
    public function analyse(string $folderPath): array
    {
        $spec = $this->openApiAnalyser->analyse();
        if ($spec == null) {
            throw new Exception("Could not find Open API documentation");
        }

        // Analyse all routes
        $routes = $this->routeResolver->resolveStrategy($folderPath);

        $endpoints = [];
        foreach ($routes as $route) {
            $endpoint = $this->fileAnalyser->analyse($route);

            array_push(
                $endpoints,
                ...$endpoint
            );
        }

        return $this->comparison->compare(
            code: $endpoints,
            spec: $spec
        );
    }
}