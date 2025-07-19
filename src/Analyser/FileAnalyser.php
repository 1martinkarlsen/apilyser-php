<?php

namespace Apilyser\Analyser;

use Apilyser\Extractor\ClassImportsExtractor;
use Apilyser\Extractor\FileClassesExtractor;
use Apilyser\Parser\NodeParser;
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

    public function analyseFile(string $filePath, string $functionName)
    {
        $endpoints = [];

        $fileContent = file_get_contents($filePath);
        $fileStmts = $this->nodeParser->parse($fileContent);

        $imports = $this->classImportsExtractor->extract($fileStmts);
        $classes = $this->fileClassesExtractor->extract($fileStmts);

        foreach ($classes as $class) {
            $function = $this->nodeFinder->findFirst($class, function ($node) use ($functionName) {
                return $node instanceof ClassMethod && $node->name->name == $functionName;
            });

            if ($function != null) {
                $endpoint = $this->endpointAnalyser->analyse(
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

        return $endpoints;
    }

    /**
     * @return EndpointDefinition[]
     */
    public function analyse(string $filePath): array
    {
        $fileContent = file_get_contents($filePath);
        $fileStmts = $this->nodeParser->parse($fileContent);

        $imports = $this->classImportsExtractor->extract($fileStmts);
        $classes = $this->fileClassesExtractor->extract($fileStmts);

        $endpoint = $this->analyzeClasses($classes, $imports);

        return $endpoint;
    }

    private function analyzeClasses(array $classes, array $imports): array
    {
        $endpoints = [];

        foreach($classes as $class) {
            $classFunctions = $this->nodeFinder->findInstanceOf($class, ClassMethod::class);
            // A private method cannot be a route.
            $filteredFunctions = array_filter(
                $classFunctions, 
                function(ClassMethod $method) {
                    return $method->isPublic();
                }
            );

            $classEndpoints = array_map(
                function(ClassMethod $method) use ($imports, $class) {
                    return $this->endpointAnalyser->analyse(
                        context: new ClassMethodContext(
                            imports: $imports,
                            class: $class,
                            method: $method
                        )
                    );
                },
                $filteredFunctions
            );

            array_push(
                $endpoints,
                ...$classEndpoints
            );
        }

        return array_filter(
            array: $endpoints,
            callback: function ($enpoint) {
                return $enpoint != null;
            }
        );
    }
}