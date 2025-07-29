<?php declare(strict_types=1);

namespace Apilyser\Comparison;

use Apilyser\Definition\EndpointDefinition;

class EndpointResult
{

    /**
     * @param EndpointDefinition $endpoint
     * @param bool $success
     * @param ValidationError[] $errors
     */
    public function __construct(
        public EndpointDefinition $endpoint,
        public bool $success,
        public array $errors = []
    ) {}

}