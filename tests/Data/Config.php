<?php

namespace Apilyser\tests\Data;

class Config
{
    public function isFeatureActive(string $key, bool $resetCacheAfterGet = false): bool
    {
        return false;
    }
}
