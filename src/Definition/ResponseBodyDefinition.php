<?php declare(strict_types=1);

namespace Apilyser\Definition;

class ResponseBodyDefinition {

    /** $name of the response property */
    private ?string $name;
    
    /** $type what type is the reponse property (e.g. 'string', 'number') */
    private ?string $type;

    /** 
     * @var ?ResponseBodyDefinition[] $children if the body is an object or array 
     * */
    private ?array $children;
    
    /** $nullable is the property nullable */
    private bool $nullable;

    public function __construct(
        ?string $name,
        ?string $type,
        ?array $children = null,
        bool $nullable = false
    )
    {
        $this->name = $name;
        $this->type = $type;
        $this->children = $children;
        $this->nullable = $nullable;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getIsNullable(): bool
    {
        return $this->nullable;
    }

    public function asArray(): array
    {
        $childArr = [];

        if ($this->children != null) {
            foreach ($this->children as $child) {
                $childArr[] = $child->asArray();
            }
        }

        return [
            'name' => $this->name,
            'type' => $this->type,
            'children' => $childArr,
            'nullable' => $this->nullable
        ];
    }

    public function toString(): string
    {
        $childArr = [];

        if ($this->children != null) {
            foreach ($this->children as $child) {
                $childArr[] = $child->toString();
            }
        }

        //$childStr = $this->children ? ", children: ". implode($childArr) : "";

        return json_encode([
            'name' => $this->name,
            'type' => $this->type,
            'children' => $childArr,
            'nullable' => $this->nullable
        ], JSON_UNESCAPED_SLASHES);

        //return "{ name: ". $this->name .", type: ". $this->type . "" . $childStr . ", nullable: ". $this->nullable ." }";
    }
}