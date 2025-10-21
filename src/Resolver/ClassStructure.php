<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
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

    public ClassLike $class;
    
    public function __construct(
        Namespace_ $namespace,
        array $imports,
        ClassLike $class
    ) {
        $this->namespace = $namespace;
        $this->imports = $imports;
        $this->class = $class;
    }
}