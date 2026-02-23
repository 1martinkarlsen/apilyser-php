<?php

namespace Apilyser\tests\Data\Endpoint;

class NavigationRouteService
{

    public function parseNavigationPathFromString(string $implodedPath): array
    {
        return explode(',', $implodedPath);
    }
}
