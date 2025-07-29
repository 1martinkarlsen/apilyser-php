<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use Apilyser\Extractor\MethodStructure;
use Apilyser\Traverser\MethodScopesTraverser;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class MethodStructureExtractor
{

    public function extract(ClassMethod $node): MethodStructure
    {
        $trav = new NodeTraverser();
        $trav->addVisitor(new ParentConnectingVisitor());
        $ast = $trav->traverse([$node]);

        $scopeTraverser = new MethodScopesTraverser();
        $t = new NodeTraverser();
        $t->addVisitor($scopeTraverser);
        $scopes = $t->traverse($ast)[0];

        return new MethodStructure($scopes, $scopeTraverser->scopes);
    }
}