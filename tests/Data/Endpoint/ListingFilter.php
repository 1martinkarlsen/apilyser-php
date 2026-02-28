<?php

namespace Apilyser\tests\Data\Endpoint;

use Symfony\Component\HttpFoundation\Request;

class ListingFilter
{
    public function getSystemFilters(Request $request): array
    {
        return [];
    }
}
