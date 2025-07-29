<?php declare(strict_types=1);

namespace Apilyser\Parser\Api;

use Apilyser\Parser\Api\ApiParser;

class HttpDelegate 
{
    /**
     * @var ApiParser[]
     */
    private array $httpParsers = [];

    public function registerParser(ApiParser $parser)
    {
        array_push(
            $this->httpParsers,
            $parser
        );
    }

    /**
     * @return ApiParser[]
     */
    public function getParsers(): array
    {
        return $this->httpParsers;
    }

    public function getRequestParser(string $fullNamespace): ?ApiParser
    {
        foreach ($this->httpParsers as $parser) {
            if ($parser->supportRequestClass($fullNamespace)) {
                return $parser;
            }
        }

        return null;
    }
}