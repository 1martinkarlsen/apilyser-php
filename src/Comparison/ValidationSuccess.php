<?php declare(strict_types=1);

namespace Apilyser\Comparison;

class ValidationSuccess extends ValidationResult
{
    public function __construct(string $message)
    {
        $this->success = true;
        $this->message = $message;
    }
}