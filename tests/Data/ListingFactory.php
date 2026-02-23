<?php

namespace Apilyser\tests\Data;

class ListingFactory
{
    public function createFiltersDTO(
        array $filters,
        array $availableFilters,
        string $gender,
        bool $hideDynamicFilters
    ): FiltersDTO {
        return new FiltersDTO();
    }

    public function createMapiListingDTO(array $responseData): ListingDTO
    {
        return new ListingDTO();
    }
}
