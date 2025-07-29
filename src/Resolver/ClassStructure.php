<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use PhpParser\Node\Stmt\Class_;

class ClassStructure
{

    /**
     * string[] $imports
     */
    public array $imports;

    public Class_ $class;
    
    public function __construct(
        array $imports,
        Class_ $class
    ) {
        $this->imports = $imports;
        $this->class = $class;
    }
}