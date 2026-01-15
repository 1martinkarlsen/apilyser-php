<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Comparison\ApiComparison;
use Apilyser\Comparison\EndpointResult;
use Apilyser\Resolver\RouteResolver;
use Exception;

class Analyser
{

    public function __construct(
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
        $routes = $this->routeResolver->resolveRoutes($folderPath);

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