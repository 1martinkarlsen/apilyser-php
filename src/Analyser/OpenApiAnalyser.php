<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\ApiSpecDefinition;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Definition\ParameterDefinition;
use Apilyser\Definition\RequestType;
use Apilyser\Definition\ResponseBodyDefinition;
use Apilyser\Definition\ResponseDefinition;
use Apilyser\Parser\YamlParser;

class OpenApiAnalyser
{

    private YamlParser $yamlParser;

    private $components;

    public function __construct(
        private string $openApiDocPath
    ) {
        $this->yamlParser = new YamlParser();
    }

    /**
     * @return ?ApiSpecDefinition
     */
    function analyse(): ?ApiSpecDefinition
    {
        $openApiSpec = $this->yamlParser->parse($this->openApiDocPath);
        if (!$openApiSpec) return null;

        $apiSpec = new ApiSpecDefinition(
            title: $openApiSpec['info']['title'] ?? 'Unknown',
            version: $openApiSpec['info']['version'] ?? 'Unknown',
            description: $openApiSpec['info']['description'] ?? ''
        );

        // Saving reusable components to be used with shared requests/response etc.
        if (array_key_exists('components', $openApiSpec)) {
            $this->components = $openApiSpec['components'];
        }

        // Get endpoints
        if (isset($openApiSpec['paths'])) {
            $endpoints = $this->parseEndpoints($openApiSpec['paths']);
            $apiSpec->setEndpoints($endpoints);
        }

        return $apiSpec;
    }

    /**
     * @return EndpointDefinition[]
     */
    private function parseEndpoints(array $endpoints): array
    {
        $result = [];

        foreach ($endpoints as $path => $methods) {
            foreach ($methods as $method => $details) {
                if (substr($path, 0, 1) != "/") {
                    $path = "/" . $path;
                }

                $api = new EndpointDefinition(
                    path: $path,
                    method: strtoupper($method),
                    parameters: $this->parseParameters($details, $methods),
                    response: $this->parseEndpointResponses($details['responses'] ?? [])
                );

                array_push(
                    $result,
                    $api
                );
            }
        }

        return $result;
    }

    /**
     * Parse parameters from endpoint definition.
     * 
     * @param array $operation The operation details
     * @param array $pathItem The entire path item (for shared parameters)
     * @return ParameterDefinition[]
     */
    private function parseParameters(array $operation, array $pathItem): array
    {
        $parameters = [];

        // Include parameters defined at path level
        $pathParameters = $pathItem['parameters'] ?? [];
        
        // Include operation-specific parameters
        $operationParameters = $operation['parameters'] ?? [];
        
        // Merge parameters
        $allParameters = array_merge($pathParameters, $operationParameters);

        foreach ($allParameters as $param) {
            $location = $this->mapParameterLocation($param['in']);
            $type = $this->mapOpenApiTypeToPhp($param['schema']['type'] ?? 'string');
            $default = $param['schema']['default'] ?? null;
            
            $parameters[] = new ParameterDefinition(
                $param['name'] ?? "",
                $type,
                $location,
                $param['required'] ?? false,
                $default
            );
        }

        // Handle request body if present
        if (array_key_exists('requestBody', $operation)) {
            $requestBody = $operation['requestBody'];
            $contentTypes = $requestBody['content'] ?? [];
            
            // Usually we'd handle the first content type, typically application/json
            foreach ($contentTypes as $content) {
                if (array_key_exists('schema', $content)) {
                    $schema = $content['schema'];
                    
                    if (array_key_exists('properties', $schema)) {
                        foreach ($schema['properties'] as $key => $value) {
                            $parameters[] = new ParameterDefinition(
                                name: $key,
                                type: array_key_exists('type', $value) ? $this->mapOpenApiTypeToPhp($value['type']) : "undefined",
                                location: RequestType::Body,
                                required: $requestBody['required'] ?? false,
                                default: null
                            );
                        }
                    }
                    
                    // Only process one content type for now
                    break;
                }
            }
        }

        return $parameters;
    }

    /**
     * @return ResponseDefinition[]
     */
    private function parseEndpointResponses(array $responses): array
    {
        $resultResponses = [];

        foreach ($responses as $status => $response) {
            $hasBody = array_key_exists('content', $response);

            $definition = new ResponseDefinition(
                type: 'application/json',
                structure: $hasBody ? $this->parseEndpointResponseBody($response['content']) : [],
                statusCode: intval($status)
            );

            array_push(
                $resultResponses,
                $definition
            );
        }

        return $resultResponses;
    }

    /**
     * @return ResponseBodyDefinition[]
     */
    private function parseEndpointResponseBody(array $content): array
    {
        if (!array_key_exists('application/json', $content)) {
            return [];
        }

        if (!array_key_exists('schema', $content['application/json'])) {
            return [];
        }

        return $this->parseSchema($content['application/json']['schema']);
    }

    /**
     * Map OpenAPI parameter location to RequestType enum
     * 
     * @param ?string $location OpenAPI parameter location
     * @return RequestType
     */
    private function mapParameterLocation(?string $location): RequestType
    {
        return match ($location) {
            'path' => RequestType::Path,
            'query' => RequestType::Query,
            'body', 'formData' => RequestType::Body,
            default => RequestType::Unknown // Default fallback
        };
    }

    /**
     * Map OpenAPI types to PHP types
     * 
     * @param string $openApiType OpenAPI type
     * @return string PHP type
     */
    private function mapOpenApiTypeToPhp(string $openApiType): string
    {
        return match ($openApiType) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            'object' => 'array', // PHP doesn't have a built-in object type distinct from array
            default => 'string'
        };
    }

    /**
     * @return ResponseBodyDefinition[]
     */
    private function parseSchema(array $schema): array
    {
        if (array_key_exists('$ref', $schema)) {
            // handle ref
            $ref = $schema['$ref'];
            $refArr = explode("/", $ref);
            if ($refArr[0] == "#" && $refArr[1] == "components") {
                // Referencing components in this file

                $reference = $this->components[$refArr[2]][$refArr[3]];
                return $this->parseSchema($reference);
            }

            return [];
        }

        // Schema does not reference, we will have to look at the type
        $type = $schema['type'];
        $hasProperties = array_key_exists('properties', $schema);

        switch ($type) {
            case 'array':
                return [];
            case 'object':
                return $hasProperties ? $this->handleSchemaProperties($schema['properties']) : [];
            default:
                return [];
        }
    }

    private function handleSchemaProperties(array $properties): array
    {
        $resultProperties = [];

        foreach ($properties as $key => $value) {
            array_push(
                $resultProperties,
                new ResponseBodyDefinition(
                    name: $key,
                    type: array_key_exists('type', $value) ? $value['type'] : null
                )
            );
        }

        return $resultProperties;
    }
}