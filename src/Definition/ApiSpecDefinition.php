<?php

namespace Apilyser\Definition;

class ApiSpecDefinition {
    
    /** @var EndpointDefinition[] */
    public array $endpoints = [];

    public function __construct(
        public string $title,
        public string $version,
        public string $description
    ) {}

    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    public function setEndpoints(array $endpoints): void
    {
        $this->endpoints = $endpoints;
    }
}