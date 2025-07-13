<?php

namespace Apilyser\Comparison;

use Apilyser\Definition\ApiSpecDefinition;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Rule\ParameterExistenceRule;
use Apilyser\Rule\ParameterTypeRule;
use Apilyser\Rule\ResponseExistenceRule;
use Apilyser\Rule\ResponsePropertyTypeRule;
use Apilyser\Rule\ResponseSchemePropertiesRule;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TODO:
 * 1. Request parameter missing from code/docs
 * 2. Request parameter type
 * 3. 
 */
class ApiComparison {

    /** @var ValidationRule[] */
    private $rules = [];

    public function __construct(public OutputInterface $output) {
        $this->rules = [
            new ResponseExistenceRule(),
            new ResponseSchemePropertiesRule(),
            new ResponsePropertyTypeRule(),
            new ParameterExistenceRule(),
            new ParameterTypeRule()
        ];
    }

    /**
     * @param EndpointDefinition[] $definitions
     * @param ApiSpecDefinition $apiSpec
     */
    public function compare(array $code, ApiSpecDefinition $spec)
    {
        $specEndpoints = $spec->endpoints;

        // Loop through docs to find routes
        foreach ($specEndpoints as $endpointSpec) {
            $this->output->writeln("-- Validating '". $endpointSpec->method ."' " . $endpointSpec->path);

            $path = $endpointSpec->path;
            $method = $endpointSpec->method;

            $endpoint = array_filter(
                $code,
                function ($definition) use ($path, $method) {
                    return ($definition->path == $path) && 
                        ($definition->method == $method) ?? $definition;
                }
            );

            if (!empty($endpoint)) {
                reset($endpoint);
                $first = current($endpoint);
                $this->compareEndpoint(spec: $endpointSpec, endpoint: $first);
            } else {
                $this->output->writeln("-- Could not find any routes for this endpoint documentation: " . $endpointSpec->path);
            }

        }

        // Find code endpoints that doesn't exist in the documentation.
        foreach ($code as $codeEndpoint) {
            $doc = null;

            foreach ($spec->endpoints as $specEndpoint) {
                if ($specEndpoint->path == $codeEndpoint->path && 
                    $specEndpoint->method == $codeEndpoint->method) {

                    $doc = $specEndpoint;
                }
            }

            if ($doc == null) {
                $this->output->writeln("-- Could not find any documentation for this code endpoint: " . $codeEndpoint->path);
            }
        }
    }

    private function compareEndpoint(EndpointDefinition $spec, EndpointDefinition $endpoint)
    {
        foreach($this->rules as $rule) {
            $result = $rule->validate($spec, $endpoint);
            if ($result instanceof ValidationError) {
                $this->output->writeln("--- " . $result->getMessage());
                foreach ($result->errors as $error) {
                    $this->output->writeln("---- " . $error);
                }
            }
        }
    }
}