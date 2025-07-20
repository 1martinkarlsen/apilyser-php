<?php

namespace Apilyser\Parser;

class Route {

    public function __construct(
        public string $method,
        public string $path,
        public ?string $controllerPath = null,
        public ?string $functionName = null
    ) {}
}