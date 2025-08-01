<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use PhpParser\Node\Stmt;

class MethodScope
{

    public ?Stmt $scope = null;

    public function __construct(
        public Stmt $statement
    ) {}
}