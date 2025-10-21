<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

class ClassStructure
{

    /**
     * Namespace_ $namespace
     */
    public Namespace_ $namespace;

    /**
     * string[] $imports
     */
    public array $imports;

    public Class_ $class;
    
    public function __construct(
        Namespace_ $namespace,
        array $imports,
        Class_ $class
    ) {
        $this->namespace = $namespace;
        $this->imports = $imports;
        $this->class = $class;
    }
}