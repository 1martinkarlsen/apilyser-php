<?php declare(strict_types=1);

namespace Apilyser\Rule;

use Apilyser\Definition\ApiSpecEndpointDefinition;
use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationResult;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ResponseStatusRule defines the rule such that there
 * should be a match between responses defined in the
 * documentation and the code implementation.
 * If an endpoint response is not defined in either
 * the documentation or the code, an error will be thrown.
 */
class ResponseExistenceRule implements ValidationRule 
{

    public function __construct() {}

    function validate(EndpointDefinition $openApiSpec, EndpointDefinition $endpoint): ValidationResult
    {
        $errors = [];
        
        $specResponses = $openApiSpec->getResponse();
        $codeResponses = $endpoint->getResponse();

        if (empty($specResponses)) {
            $errMessage = "Endpoint documentation '" . $openApiSpec->path . "' does not have any responses";
            array_push(
                $errors,
                $errMessage
            );
        }

        if (empty($codeResponses)) {
            $errMessage = "Endpoint route '" . $openApiSpec->path . "' does not have any responses";
            array_push(
                $errors,
                $errMessage
            );
        }

        /**
         * Looping through doc responses to find matching route response
         */
        foreach ($specResponses as $spec) {
            $matching = array_filter(
                $codeResponses,
                function ($response) use ($spec) {
                    return $spec->statusCode == $response->statusCode
                        && $spec->type == $response->type ?? $response;
                }
            );

            if (count($matching) == 0) {
                $errMessage = "Endpoint documentation '". $openApiSpec->path ."' does not have a matching route response with status: " . $spec->statusCode;
                array_push(
                    $errors,
                    $errMessage
                );
            }
        }

        /**
         * Looping through route responses to find a matching doc response
         */
        foreach ($codeResponses as $code) {
            $matching = array_filter(
                array: $specResponses,
                callback: function ($response) use ($code) {
                    return $response->statusCode == $code->statusCode
                        && $response->type == $code->type ?? $response;
                }
            );

            if (empty($matching)) {
                $errMessage = "Endpoint route '". $endpoint->path ."' does not have a matching documentation response with status: " . $code->statusCode;
                array_push(
                    $errors,
                    $errMessage
                );
            }
        }

        if (!empty($errors)) {
            return new ValidationError(
                errorType: "ResponseExistenceRule",
                message: "ResponseExistenceRule failed at " . $openApiSpec->path,
                errors: $errors
            );
        }

        return new ValidationSuccess("Validation passed");
    }
}