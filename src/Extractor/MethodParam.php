<?php

namespace Apilyser\Extractor;

class MethodParam
{

    public function __construct(
        public string $name, // variable name
        public string $type, // Class name eller 'string', 'int', 'Request'
        public bool $isBuiltinType, // Is namespaced ('string', 'int')
        public ?string $fullNamespace = null, // Only for objects
        public bool $isNullable = false,
        public mixed $defaultValue = null
    ) {}
}