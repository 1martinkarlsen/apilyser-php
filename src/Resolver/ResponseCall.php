<?php

namespace Apilyser\Resolver;

class ResponseCall
{

    /**
     * @param string $type (ex "application/json")
     * @param ?ResponseBodyDefinition[] $structure
     * @param ?int $statusCode
     * @param bool $hasBeenSent
     */
    public function __construct(
        public string $type,
        public ?array $structure,
        public ?int $statusCode,
        public bool $hasBeenSent = false
    ) {}
}