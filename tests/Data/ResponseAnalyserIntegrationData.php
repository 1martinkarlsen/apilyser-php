<?php declare(strict_types=1);

namespace Apilyser\tests\Data;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseAnalyserIntegrationData
{

    private ServiceResponseAnalyserIntegrationData $service;

    public function __construct()
    {
        $this->service = new ServiceResponseAnalyserIntegrationData();
    }

    public function withOneDirectReturn(): Response
    {
        return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
    }

    public function withMultipleDirectReturn(): Response
    {
        $x = 1;
        if ($x > 0) {
            return new JsonResponse(["id" => 1, "user_name" => "Test"], 200);
        } else if ($x > 0 && $x < 10) {
            return new JsonResponse(["id" => 1, "user_name" => "Test", "email" => "hej@hej.dk"], 200);
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

    public function withServiceCallReturn() : Response
    {
        return $this->service->serviceCallReturn();
    }

    public function withVariableStatusCode(): Response
    {
        $statusCode = 401;
        return new JsonResponse(null, $statusCode);
    }

    private $classScopedVariableStatusCode = 401;
    public function withClassScopedVariableStatusCode(): Response
    {
        return new JsonResponse(null, $this->classScopedVariableStatusCode);
    }

    public function withConstantStatusCode(): Response
    {
        return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
    }

    public function withParameterVariableStatusCode(): Response
    {
        return $this->privateParameterVariableStatusCode(200);
    }

    private function privateParameterVariableStatusCode(int $statusCode): Response
    {
        return new JsonResponse(null, $statusCode);
    }

    public function withMethodCallStatusCode(): Response
    {
        return new JsonResponse(null, $this->privateWithMethodCallStatusCode());
    }

    private function privateWithMethodCallStatusCode(): int
    {
        return 200;
    }

    public function withDefaultStatusCode(): Response
    {
        return new JsonResponse();
    }

    public function withDirectArrayBody(): Response
    {
        return new JsonResponse(["id" => 1], 200);
    }

    public function withDirectNullBody(): Response
    {
        return new JsonResponse(null, 200);
    }

    public function withDirectEmptyArrayBody(): Response
    {
        return new JsonResponse([], 200);
    }

    public function withDirectArrayWithVariablesBody(): Response
    {
        $id = 1;
        return new JsonResponse(
            [
                "id" => $id, 
                "name" => $this->service->getProperty(),
                "email" => $this->getArrayProperty()
            ], 
            200
        );
    }

    private function getArrayProperty(): string
    {
        return "Hello";
    }

    public function withVariableArrayBody(): Response
    {
        $data = ["id" => 1];
        return new JsonResponse($data, 200);
    }

    public function withMethodCallBody(): Response
    {
        return new JsonResponse($this->privateWithMethodCallBody(), 200);
    }

    private function privateWithMethodCallBody(): array
    {
        $data = ["id" => 1];
        return $data;
    }
    
    public function withMultipleEarlyReturns(): Response
    {
        $valid = true;
        $authorized = true;
        
        if (!$valid) {
            return new JsonResponse(['error' => 'Invalid request'], 400);
        }
        
        if (!$authorized) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        return new JsonResponse(['success' => true, 'data' => 'result'], 200);
    }

    public function withMultipleDtoModelReturn(): Response
    {
        $data = $this->service->getConditionalResponse();
        return new JsonResponse($data, 200);
    }

    public function withTryCatchBlock(): Response
    {
        try {
            $data = ['result' => 'success'];
            return new JsonResponse($data, 200);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Not found'], 404);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Server error'], 500);
        }
    }
    
    public function withSwitchStatement(): Response
    {
        $type = 'success';
        
        switch ($type) {
            case 'success':
                return new JsonResponse(['result' => 'ok'], 200);
            case 'not_found':
                return new JsonResponse(['error' => 'Not found'], 404);
            case 'bad_request':
                return new JsonResponse(['error' => 'Bad request'], 400);
            default:
                return new JsonResponse(['error' => 'Server error'], 500);
        }
    }

    /**
     * Test case: Ternary operator for status code
     * Expected: Should find both 200 and 400
     * Confidence: Medium (conditional)
     */
    public function withTernaryStatusCode(): Response
    {
        $success = true;
        return new JsonResponse(
            ['data' => 'test'], 
            $success ? 200 : 400
        );
    }

    /**
     * Test case: Ternary operator for body
     * Expected: Should detect both possible body structures OR mark as medium confidence
     * Confidence: Medium
     */
    public function withTernaryBody(): Response
    {
        $success = true;
        return new JsonResponse(
            $success ? ['result' => 'success'] : ['error' => 'failed'],
            200
        );
    }

    /**
     * Test case: Nested ternary (edge case)
     * Expected: Should find 200, 400, 500 OR mark as low confidence
     * Confidence: Low/Medium
     */
    public function withNestedTernary(): Response
    {
        $status = 1;
        return new JsonResponse(
            ['data' => 'test'],
            $status === 1 ? 200 : ($status === 2 ? 400 : 500)
        );
    }

    /**
     * Test case: Variable reassigned multiple times
     * Expected: Should find all 3 possible status codes (200, 400, 401)
     * Confidence: Medium (complex flow)
     */
    public function withReassignedStatusCode(): Response
    {
        $code = 200;
        
        $hasError = false;
        if ($hasError) {
            $code = 400;
        }
        
        $unauthorized = false;
        if ($unauthorized) {
            $code = 401;
        }
        
        return new JsonResponse(['status' => 'done'], $code);
    }

    /**
     * Test case: Null coalescing operator
     * Expected: Should detect default 200 + possible other values
     * Confidence: Medium
     */
    public function withNullCoalescingStatusCode(): Response
    {
        $statusCode = null;
        return new JsonResponse(
            ['data' => 'test'],
            $statusCode ?? 200
        );
    }
}