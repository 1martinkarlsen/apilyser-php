<?php declare(strict_types=1);

namespace Apilyser\Parser;

use Symfony\Component\Yaml\Yaml;

class YamlParser
{

    /**
     * Open yaml file and returns content.
     */
    function parse(string $input): array|null
    {
        try {
            $openApiSpec = Yaml::parseFile($input);
            return $openApiSpec;
        } catch (\Exception $e) {
            return null;
        }
    }
}