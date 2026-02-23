<?php

namespace Apilyser\tests\Data;

class NavigationRouteService
{

    public function parseNavigationPathFromString(string $implodedPath): array
    {
        return explode(',', $implodedPath);
    }
}
