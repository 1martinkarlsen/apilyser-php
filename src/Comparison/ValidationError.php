<?php

namespace Apilyser\Comparison;

class ValidationError extends ValidationResult
{

    /**
     * @var string[] $errors
     */
    public array $errors;

    public function __construct(string $message, array $errors)
    {
        $this->success = false;
        $this->message = $message;
        $this->errors = $errors;
    }
}