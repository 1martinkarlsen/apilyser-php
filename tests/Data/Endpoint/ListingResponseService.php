<?php declare(strict_types=1);

namespace Apilyser\tests\Data\Endpoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ListingResponseService
{
    public function getParameters(Request $request, array $responseData): array
    {
        return [];
    }

    public function getResponse(Request $request, array $responseData): Response
    {
        return new Response(json_encode("'test': 'hej'"));
    }
}
