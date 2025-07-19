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

    /**
     * @return EndpointDefinition[]
     */
    public function analyse(string $filePath, string $functionName)
    {
        $endpoints = [];

        $fileContent = file_get_contents($filePath);
        $fileStmts = $this->nodeParser->parse($fileContent);

        $imports = $this->classImportsExtractor->extract($fileStmts);
        $classes = $this->fileClassesExtractor->extract($fileStmts);

        return $this->analyseClasses($classes, $imports, $functionName);
    }

    private function analyseClasses(array $classes, array $imports, string $functionName): array
    {
        $endpoints = [];

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

        return array_filter(
            array: $endpoints,
            callback: function ($enpoint) {
                return $enpoint != null;
            }
        );
    }
}