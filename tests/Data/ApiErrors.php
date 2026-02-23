<?php

namespace Apilyser\tests\Data;

use Symfony\Component\HttpFoundation\Response;

class ApiErrors
{
    public function throwApiErrorException(
        string $apiError,
        int $httpStatus = Response::HTTP_OK,
        array $validationErrors = [],
    ): void {

    }
}
