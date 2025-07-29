<?php declare(strict_types=1);

namespace Apilyser\Definition;

use Apilyser\Definition\ResponseBodyDefinition;

class ResponseDefinition
{
    /**
     * @param string $type content type, e.g. 'application/json'
     * @param ResponseBodyDefinition[]|null $structure list of body response, e.g. ['id' => 'number']
     * @param int $statusCode
     */
    public function __construct(
        public string $type,
        public ?array $structure,
        public int $statusCode
    ) {}

    public function asArray(): array
    {
        return [
            'type' => $this->type,
            'structure' => $this->structure,
            'statusCode' => $this->statusCode
        ];
    }

    // Methods to compare with OpenAPI response spec
    public function toString(): string
    {
        $dataArr = [];

        if ($this->structure != null) {
            foreach($this->structure as $struct) {
                array_push($dataArr, $struct->asArray());
            }
        }

        return json_encode(
            [
                'type' => $this->type,
                'structure' => $dataArr,
                'statusCode' => $this->statusCode
            ],
            JSON_UNESCAPED_SLASHES
        );

        //$struct = implode($dataArr);
        //return "{ 'type': ". $this->type .", 'structure': ". ($this->structure == null ? 'null' : $struct) .", 'statusCode': ". $this->statusCode ." }";
    }
}