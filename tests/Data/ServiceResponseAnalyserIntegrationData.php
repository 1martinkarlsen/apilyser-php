<?php declare(strict_types=1);

namespace Apilyser\tests\Data;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceResponseAnalyserIntegrationData
{
    function serviceCallReturn(): Response
    {
        return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
    }

    function getProperty(): string
    {
        return "hello";
    }
}