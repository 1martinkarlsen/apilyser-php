<?php

namespace Apilyser\Comparison;

abstract class ValidationResult 
{
    /** @var bool $success */
    protected bool $success;
    
    /** @var string $message */
    protected string $message;

    public function isSuccess(): bool 
    {
        return $this->success;
    }

    public function getMessage(): string 
    {
        return $this->message;
    }
}
