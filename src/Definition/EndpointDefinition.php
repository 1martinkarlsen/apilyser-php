<?php declare(strict_types=1);

namespace Apilyser\Definition;

class EndpointDefinition
{

    /**
     * @param string $path is the full path for the endpoint
     * @param string $method is the endpoint method, eg 'GET', 'POST', 'PUT
     * @param ?ParameterDefinition[] $parameters
     * @param ?ResponseDefinition[] $response
     */
    public function __construct(
        public string $path,
        public string $method,
        private ?array $parameters,
        private ?array $response
    ) {}

    /**
     * @return ?ParameterDefinition[]
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @return ?ResponseDefinition[]
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    public function toString(): string
    {
        /*if ($this->parameters != null) {
            $params = implode(array_map(
                function ($param) {
                    return $param->toString();
                },
                $this->parameters
            ));
            $paramStr = "[ ". $params ." ]";
        } else {
            $paramStr = "null";
        }

        if ($this->response != null) {
            $res = implode(
                array_map(
                    function ($resDif) {
                        return $resDif->toString();
                    },
                    $this->response
                )
            );
            $resStr = "[ ". $res ." ]";
        } else {
            $resStr = "null";
        }

        $parameters = [];
        foreach ($this->parameters as $param) {
            array_push($parameters, $param->toString());
        }

        $responses = [];
        foreach ($this->response as $res) {
            array_push($responses, $res->toString());
        }*/

        return json_encode(
            [
                'path' => $this->path,
                'method' => $this->method,
                'parameters' => $this->parameters,
                'responses' => $this->response
            ],
            JSON_UNESCAPED_SLASHES
        );
    }
}