<?php

namespace Apilyser\Rule;

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationResult;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;

/**
 * ParameterExistenceRule defines the rule such that the parameters
 * in the documentation should match the parameters in the code.
 * This means that an error will be thrown if either the documentation
 * or the code is missing a parameter.
 */
class ParameterExistenceRule implements ValidationRule {

    function validate(EndpointDefinition $openApiSpec, EndpointDefinition $endpoint): ValidationResult
    {
        $errors = [];
        
        $specParameters = $openApiSpec->getParameters();
        $codeParameters = $endpoint->getParameters();

        // Find parameters missing from the code
        if ($specParameters != null) {
            foreach ($specParameters as $spec) {
                $codeParam = null;
                foreach ($codeParameters as $code) {
                    if ($code->getName() == $spec->getName() 
                        && $code->getLocation() == $spec->getLocation()) {
                        $codeParam = $code;
                    }
                }

                if ($codeParam == null) {
                    array_push(
                        $errors,
                        "Endpoint parameter " . $spec->getName() . " doesn't exist in the code."
                    );
                }
            }
        }

        // Find parameters in code, missing from documentation
        if ($codeParameters != null) {
            foreach ($codeParameters as $code) {
                $docParam = null;
                foreach ($specParameters as $spec) {
                    if ($spec->getName() == $code->getName() 
                        && $spec->getLocation() == $code->getLocation()) {
                        $docParam = $spec;
                    }
                }

                if ($docParam == null) {
                    array_push(
                        $errors,
                        "Endpoint parameter " . $code->getName() . " doesn't exist in the documentation."
                    );
                }
            }
        }

        if (!empty($errors)) {
            return new ValidationError(
                message: "ParameterExistenceRule failed at " . $openApiSpec->path, 
                errors: $errors
            );
        }

        return new ValidationSuccess("");
    }
}