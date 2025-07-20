<?php

namespace Apilyser\Analyser;

use Apilyser\Extractor\ClassImportsExtractor;
use Apilyser\Extractor\FileClassesExtractor;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

final class FileAnalyser
{

    public function __construct(
        private NodeParser $nodeParser,
        private NodeFinder $nodeFinder,
        private EndpointAnalyser $endpointAnalyser,
        private FileClassesExtractor $fileClassesExtractor,
        private ClassImportsExtractor $classImportsExtractor
    ) {}

    /**
     * @return EndpointDefinition[]
     */
    public function analyse(Route $route)
    {
        $fileContent = file_get_contents($route->controllerPath);
        $fileStmts = $this->nodeParser->parse($fileContent);

        $imports = $this->classImportsExtractor->extract($fileStmts);
        $classes = $this->fileClassesExtractor->extract($fileStmts);

        return $this->analyseClasses($route, $classes, $imports);
    }

    private function analyseClasses(Route $route, array $classes, array $imports): array
    {
        $endpoints = [];

        $functionName = $route->functionName;
        foreach ($classes as $class) {
            $function = $this->nodeFinder->findFirst($class, function ($node) use ($functionName) {
                return $node instanceof ClassMethod && $node->name->name == $functionName;
            });

            if ($function != null) {
                $endpoint = $this->endpointAnalyser->analyse(
                    route: $route,
                    context: new ClassMethodContext(
                        imports: $imports,
                        class: $class,
                        method: $function
                    )
                );

                array_push(
                    $endpoints,
                    $endpoint
                );
            }
        }

        return array_filter(
            array: $endpoints,
            callback: function ($enpoint) {
                return $enpoint != null;
            }
        );
    }
}