<?php

namespace Apilyser\Rule;

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationResult;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Definition\ResponseBodyDefinition;
use Apilyser\Definition\ResponseDefinition;

/**
 * ResponsePropertyTypeRule defines the rule such that the code
 * implementation of a endpoint response body should match the documentation
 * on the types.
 * This rule uses the documentation as the source of truth and will therefore
 * only throw an error if the property type doesn't match
 * with the documentation.
 */
class ResponsePropertyTypeRule implements ValidationRule {

    function validate(EndpointDefinition $openApiSpec, EndpointDefinition $endpoint): ValidationResult 
    {
        $errors = [];
        
        $specResponses = $openApiSpec->getResponse();
        $codeResponses = $endpoint->getResponse();

        foreach ($specResponses as $spec) {
            foreach ($codeResponses as $code) {
                if ($code->statusCode == $spec->statusCode && $code->type == $spec->type) {
                    $result = $this->validateResponse($spec, $code);
                    if ($result != null) {
                        array_push(
                            $errors,
                            $result
                        );
                    }
                }
            }
        }

        if (!empty($errors)) {
            return new ValidationError(
                message: "ResponsePropertyTypeRule failed at " . $openApiSpec->path, 
                errors: $errors
            );
        }

        return new ValidationSuccess("");
    }

    private function validateResponse(ResponseDefinition $spec, ResponseDefinition $code): ?string
    {
        $errors = [];
        $specProps = $spec->structure;
        $codeProps = $code->structure;

        foreach ($specProps as $prop) {
            $code = $this->findResponsePropertyByName($prop->getName(), $codeProps);
            if ($code != null) {
                if ($prop->getType() != $code->getType()) {
                    array_push(
                        $errors,
                        $prop->getName()
                    );
                }
            }
        }

        if (!empty($errors)) {
            return implode(", ", $errors);
        }

        return null;
    }

    private function findResponsePropertyByName(string $name, array $properties): ?ResponseBodyDefinition
    {
        $result = array_filter(
            $properties,
            function ($prop) use ($name) {
                return $prop->getName() == $name;
            }
        );

        return $result[0] ?? null;
    }
    
}