<?php

namespace Apilyser\Extractor;

use PhpParser\Node\Attribute;

class AttributeExtractor
{

    public function __construct() {}

    /**
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     */
    public function extract(array $attrGroups, string $attrName): ?Attribute
    {
        foreach($attrGroups as $attrGroup) {
            $groupAttrs = $attrGroup->attrs;
            foreach($groupAttrs as $attr) {
                if ($attr->name == $attrName) {
                    return $attr;
                }
            }
        }

        return null;
    }
}