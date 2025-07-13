<?php

namespace Apilyser\Rule;

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationResult;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;

/**
 * ParameterTypeRule defines the rule such that the code implementation of a parameter
 * should have a matching type as defined in the documentation.
 */
class ParameterTypeRule implements ValidationRule
{

    function validate(EndpointDefinition $openApiSpec, EndpointDefinition $endpoint): ValidationResult
    {
        $errors = [];
        
        $specParameters = $openApiSpec->getParameters();
        $codeParameters = $endpoint->getParameters();

        foreach ($specParameters as $spec) {
            foreach ($codeParameters as $code) {
                if ($code->getName() == $spec->getName()) {
                    if ($code->getType() != $spec->getType()) {
                        array_push(
                            $errors,
                            "Parameter '" . $spec->getName() . "' has wrong type"
                        );
                    }
                }
            }
        }

        if (!empty($errors)) {
            return new ValidationError("ParameterTypeRule failed at " . $openApiSpec->path, $errors);
        }

        return new ValidationSuccess("");
    }
}