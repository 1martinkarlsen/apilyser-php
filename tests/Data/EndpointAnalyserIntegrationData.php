<?php

namespace Apilyser\tests\Data;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EndpointAnalyserIntegrationData
{

    public function __construct(
        private NavigationRouteService $routeService,
        private ListingFilter $listingFilterService,
        private ApiErrors $apiErrorService,
        private ServerSideListingResponse $serverSideListingResponseService,
        private ListingResponseService $listingResponseService,
        private ListingFactory $listingFactory,
        private Config $configService,
        private TestLogger $logger,
    ) {}

    public function testExample(Request $request): Response
    {
        $queryPath = $request->query->get('path');

        if (isset($queryPath) && preg_match("/^[,0-9]+$/", $queryPath)) {
            $request->attributes->set('path', $this->routeService->parseNavigationPathFromString($queryPath));
        }

        $systemFilters = $this->listingFilterService->getSystemFilters($request);

        if (empty($systemFilters['path'])) {
            $this->apiErrorService->throwApiErrorException("NOT FOUND", Response::HTTP_NOT_FOUND);
        }

        $response = null;
        try {
            $responseData = $this->serverSideListingResponseService->getListingResponse(
                $request, 
                $systemFilters,
                true
            );
            $responseData = $this->listingResponseService->getParameters($request, $responseData);
            $response = $this->listingResponseService->getResponse($request, $responseData);

            if ($request->query->get('dry_run')) {
                return $response;
            }

            if ($request->query->get('api_filters')) {
                $response->setContent(
                    json_encode(
                        $this->listingFactory->createFiltersDTO(
                            $responseData['filters'],
                            $responseData['availableFilters'],
                            $responseData['gender'],
                            $responseData['hideDynamicFilters']
                        ),
                        JSON_THROW_ON_ERROR
                    ),
                );

                return $response;
            }

            $listingDTO = $this->listingFactory->createMapiListingDTO($responseData);
            $data = [
                'type' => strtoupper($listingDTO->getType()),
                'navigation_id' => $listingDTO->getNavigationId(),
                'navigation_name' => $listingDTO->getNavigationName(),
                'tracking_url' => $listingDTO->getTrackingUrl(),
                'path' => $listingDTO->getPath(),
                'filter_reset' => $listingDTO->isFilterReset(),
                'breadcrumbs' => $listingDTO->getBreadcrumbs(),
                'listing_data' => $listingDTO->getListingData(),
                'pagination' => $listingDTO->getPagination(),
                'categories' => $listingDTO->getCategories(),
                'campaign_id' => $listingDTO->getCampaignId(),
                'go_page_view_tracking' => $listingDTO->getGoPageViewTracking()?->toArray() ?? []
            ];

            if ($this->configService->isFeatureActive("PLT-104394-mapi-listing-include-filters")) {
                $filtersDTO = $this->listingFactory->createFiltersDTO(
                    $responseData['filters'],
                    $responseData['availableFilters'],
                    $responseData['gender'],
                    $responseData['hideDynamicFilters']
                );
                $data['filters'] = $filtersDTO->getFilters();
            }

            $response->setContent(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (\InvalidArgumentException) {
            $this->apiErrorService->throwApiErrorException("INVALID PARAMS", Response::HTTP_NOT_FOUND);
        } catch (\JsonException) {
            $this->apiErrorService->throwApiErrorException("INVALID PARAMS", Response::HTTP_NOT_FOUND);
        } catch (\Apilyser\tests\Data\ListingServiceClientException | ClientErrorException $e) {
            $this->logger->error("Error");
            $this->apiErrorService->throwApiErrorException("INTERNAL SERVER ERROR", $e->getCode());
        }

        if (null === $response) {
            $this->apiErrorService->throwApiErrorException("INTERNAL SERVER ERROR", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}

class NavigationRouteService
{

    public function parseNavigationPathFromString(string $implodedPath): array
    {
        return explode(',', $implodedPath);
    }
}

class ListingFilter
{
    public function getSystemFilters(Request $request): array
    {
        return [];
    }
}

class ApiErrors
{
    public function throwApiErrorException(
        string $apiError,
        int $httpStatus = Response::HTTP_OK,
        array $validationErrors= [],
    ): void {

    }
}

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

class ListingResponseService
{
    public function getParameters(Request $request, array $responseData): array
    {
        return [];
    }

    public function getResponse(Request $request, array $responseData): Response
    {
        return new Response();
    }
}

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

class FiltersDTO
{
    /**
     * @return FilterDTO[]
     */
    public function getFilters(): array
    {
        return [];
    }
}

class FilterDTO
{}

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

class ListingGoPageViewEventDTO
{
    public function toArray(): array
    {
        return [];
    }
}

class Config
{
    public function isFeatureActive(string $key, bool $resetCacheAfterGet = false): bool
    {
        return false;
    }
}

class ListingServiceClientException extends Exception
{}

class ClientErrorException extends Exception
{}

class TestLogger
{
    public function error(string $msg) {

    }
}
