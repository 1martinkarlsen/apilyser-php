<?php

namespace Apilyser\Parser\Route;

interface RouteStrategy
{

    public function canHandle(string $rootPath): bool;
    
    public function parseRoutes(string $rootPath): array;
}