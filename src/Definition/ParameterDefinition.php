<?php declare(strict_types=1);

namespace Apilyser\Definition;

class ParameterDefinition
{
    /**
     * @param string $name The parameter name (e.g., 'userId', 'sortBy')
     * @param string $type PHP type (e.g., 'string', 'int', 'array')
     * @param RequestType $location Where parameter appears: 'path' (/users/{userId}) or 'query' (/users?sortBy=name)
     * @param bool $required Is this parameter mandatory
     * @param mixed $default Default value if parameter is not provided
     */
    public function __construct(
        private string $name,
        private string $type,
        private RequestType $location,
        private bool $required,
        private mixed $default,
    ) {}

    // Methods to compare with OpenAPI parameter spec
    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLocation(): RequestType
    {
        return $this->location;
    }

    public function toString(): string
    {
        return json_encode(
            [
                'name' => $this->name,
                'type' => $this->type,
                'location' => $this->location->name,
                'required' => $this->required,
                'default' => $this->default
            ]
        );
        //return "{ name: " . $this->name . ", location: " . $this->location->name . ", type: " . $this->type . " }";
    }
}