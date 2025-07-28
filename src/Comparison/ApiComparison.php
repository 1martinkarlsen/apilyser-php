<?php

namespace Apilyser\Comparison;

use Apilyser\Definition\ApiSpecDefinition;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Rule\ValidationRule;
use Apilyser\Rule\ParameterExistenceRule;
use Apilyser\Rule\ParameterTypeRule;
use Apilyser\Rule\ResponseExistenceRule;
use Apilyser\Rule\ResponsePropertyTypeRule;
use Apilyser\Rule\ResponseSchemePropertiesRule;
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
     * @param EndpointDefinition[] $code
     * @param ApiSpecDefinition $spec
     * 
     * @return EndpointResult[]
     */
    public function compare(array $code, ApiSpecDefinition $spec): array
    {
        $results = [];
        $specEndpoints = $spec->endpoints;

        // Loop through docs to find routes
        foreach ($specEndpoints as $endpointSpec) {
            $this->output->writeln("<info>". $endpointSpec->method ." " . $endpointSpec->path . "</info>");

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
                $errors = $this->compareEndpoint(spec: $endpointSpec, endpoint: $first);

                array_push(
                    $results,
                    new EndpointResult(
                        endpoint: $endpointSpec,
                        success: empty($errors),
                        errors: $errors
                    )
                );
            } else {
                array_push(
                    $results,
                    new EndpointResult(
                        endpoint: $endpointSpec,
                        success: false,
                        errors: [
                            new ValidationError(
                                errorType: "MissingEndpoint",
                                message: "Documentation for endpoint " . $endpointSpec->method . " " . $endpointSpec->path . " doesn't have any implementation in code.",
                                errors: []
                            )
                        ]
                    )
                );
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
                array_push(
                    $results,
                    new EndpointResult(
                        endpoint: $codeEndpoint,
                        success: false,
                        errors: [
                            new ValidationError(
                                errorType: "MissingDocumentation",
                                message: "Endpoint " . $codeEndpoint->method . " " . $codeEndpoint->path . " doesn't have documentation.",
                                errors: []
                            )
                        ]
                    )
                );
            }
        }

        return $results;
    }

    /**
     * @param EndpointDefinition $spec
     * @param EndpointDefinition $endpoint
     * 
     * @return ValidationError[]
     */
    private function compareEndpoint(EndpointDefinition $spec, EndpointDefinition $endpoint): array
    {
        $validationErrors = [];

        foreach($this->rules as $rule) {
            $result = $rule->validate($spec, $endpoint);
            if ($result instanceof ValidationError) {
                array_push(
                    $validationErrors,
                    $result
                );
            }
        }

        return $validationErrors;
    }
}