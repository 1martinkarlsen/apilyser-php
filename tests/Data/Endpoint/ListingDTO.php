<?php

namespace Apilyser\tests\Data\Endpoint;

class ListingDTO
{
    public function getType(): string
    {
        return "";
    }

    public function getNavigationId(): int
    {
        return 0;
    }

    public function getNavigationName(): string
    {
        return "";
    }

    public function getTrackingUrl(): string
    {
        return "";
    }

    public function getPath(): array
    {
        return [];
    }

    public function isFilterReset(): bool
    {
        return false;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getListingData(): array
    {
        return [];
    }

    public function getPagination(): array
    {
        return [];
    }

    public function getCategories(): array
    {
        return [];
    }

    public function getCampaignId(): int|null
    {
        return null;
    }

    public function getGoPageViewTracking(): ListingGoPageViewEventDTO|null
    {
        return null;
    }
}
