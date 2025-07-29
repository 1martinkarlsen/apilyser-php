<?php declare(strict_types=1);

namespace Apilyser\Rule;

use Apilyser\Definition\EndpointDefinition;
use Apilyser\Comparison\ValidationResult;

interface ValidationRule {

    function validate(EndpointDefinition $spec, EndpointDefinition $endpoint): ValidationResult;
}