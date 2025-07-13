<?php

namespace Apilyser\Definition;

class NewClassResponseParameter
{

    public function __construct(
        public string $statusCodeName,
        public string $bodyName,
        public int $statusCodeIndex,
        public int $bodyIndex
    ) {}
}