<?php

namespace Apilyser\tests\Data\Endpoint;

use Symfony\Component\HttpFoundation\Request;

class ServerSideListingResponse
{
    public function getListingResponse(
        Request $request,
        array $systemFilters,
        bool $productCardsRedesign = false
    ): array
    {
        return [];
    }
}
