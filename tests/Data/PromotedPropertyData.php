<?php declare(strict_types=1);

namespace Apilyser\tests\Data;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PromotedPropertyData
{
    public function __construct(
        private ServiceResponseAnalyserIntegrationData $service
    ) {}

    public function withPromotedPropertyServiceCall(): Response
    {
        $response = $this->service->serviceCallReturn();

        return $response;
    }

    public function withPromotedPropertyInTryCatch(): Response
    {
        $response = null;
        try {
            $response = $this->service->serviceCallReturn();
        } catch (\Exception $e) {
            $response = new JsonResponse(['error' => 'fail'], 500);
        }

        return $response;
    }
}
