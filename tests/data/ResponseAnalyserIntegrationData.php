<?php declare(strict_types=1);

namespace Apilyser\tests\Analyser;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseAnalyserIntegrationData
{

    public function withOneDirectReturn(): Response
    {
        return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
    }

    public function withMultipleDirectReturn(): Response
    {
        $x = 1;
        if ($x > 0) {
            return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
        } else {
            return new JsonResponse(null, 401);
        }
    }

    public function withVariableReturn() : Response
    {
        $response = new JsonResponse(["id" => 1, "user_name" => "Test"], 200);

        return $response;
    }

    public function withOuterScopeVariableReturn() : Response
    {
        $x = 1;
        if ($x > 0) {
            $response = new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
        } else {
            $response = new JsonResponse(null, 401);
        }

        return $response;
    }

    public function withMethodCallReturn() : Response
    {
        return $this->privateMethodCallReturn();
    }

    private function privateMethodCallReturn(): Response
    {
        return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
    }
}