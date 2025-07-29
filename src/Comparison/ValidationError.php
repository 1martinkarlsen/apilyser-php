<?php declare(strict_types=1);

namespace Apilyser\Comparison;

class ValidationError extends ValidationResult
{

    /**
     * @var string $errorType
     */
    public string $errorType;

    /**
     * @var string[] $errors
     */
    public array $errors;

    public function __construct(string $errorType, string $message, array $errors)
    {
        $this->success = false;
        $this->errorType = $errorType;
        $this->message = $message;
        $this->errors = $errors;
    }
}