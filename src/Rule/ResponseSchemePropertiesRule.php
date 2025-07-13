<?php

namespace Apilyser\Rule;

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationResult;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Definition\ResponseDefinition;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ResponseSchemePropertiesRule defines the rule such that there
 * should not be any missing response body properties in
 * either the documentation or the code implementation.
 */
class ResponseSchemePropertiesRule implements ValidationRule
{
    public function __construct() {}

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
                            implode(", ", $result)
                        );
                    }
                }
            }
        }

        if (!empty($errors)) {
            return new ValidationError(
                message: "ResponseSchemePropertiesRule failed at " . $openApiSpec->path, 
                errors: $errors
            );
        }
        return new ValidationSuccess("");
    }

    /**
     * @return ?string[]
     */
    private function validateResponse(ResponseDefinition $spec, ResponseDefinition $code): ?array
    {
        $specProps = $spec->structure;
        $codeProps = $code->structure;

        $missingProps = $this->findMissingProperties(
            statusCode: $spec->statusCode,
            specProps: $specProps ?? [],
            codeProps: $codeProps ?? []
        );

        return $missingProps;
    }

    /**
     * @return ?string[]
     */
    private function findMissingProperties(int $statusCode, array $specProps, array $codeProps): ?array
    {
        $errors = [];

        $mappedSpecProps = array_map(
            array: filter_not_null($specProps),
            callback: function ($prop) {
                return $prop->getName();
            }
        );
        $mappedCodeProps = array_map(
            array: filter_not_null($codeProps),
            callback: function ($prop) {
                return $prop->getName();
            }
        );

        // Properties in the spec that is missing from the code
        $missingCodeProps = array_diff(
            $mappedSpecProps,
            $mappedCodeProps
        );

        // Properties in the code that is missing in the spec
        $missingSpecProps = array_diff(
            $mappedCodeProps,
            $mappedSpecProps
        );

        // If any documentation props doesn't exist in the code
        if (!empty($missingCodeProps)) {
            array_push(
                $errors,
                "Response schema properties for status $statusCode missing in implementation: " . 
                        implode(", ", $missingCodeProps)
            );
        }

        // If any code props doesn't exist in the documentation
        if (!empty($missingSpecProps)) {
            array_push(
                $errors,
                "Response schema properties for status $statusCode missing in documentation: " . 
                        implode(", ", $missingSpecProps)
            );
        }

        if (!empty($errors)) {
            return $errors;
        }

        return null;
    }

}

function filter_not_null(array $array): array
{
    return array_filter(
        array: $array,
        callback: function ($item) {
            return $item != null && $item->getName() != null;
        }
    );
}
