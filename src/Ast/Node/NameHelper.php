<?php

namespace Apilyser\Ast\Node;

use PhpParser\Node\Name;

class NameHelper
{

    public static function getName(Name $name): string
    {
        if (property_exists($name, 'name') && null !== $name->name) {
            return $name->name;
        } else {
            return $name->toString();
        }
    }
}