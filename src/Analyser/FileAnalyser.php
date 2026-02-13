<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\EndpointDefinition;
use Apilyser\Ast\ImportFinder;
use Apilyser\Ast\ClassFinder;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route;
use Apilyser\Util\Logger;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;

final class FileAnalyser
{

    public function __construct(
        private Logger $logger,
        private NodeParser $nodeParser,
        private NodeFinder $nodeFinder,
        private EndpointAnalyser $endpointAnalyser,
        private ClassFinder $classFinder,
        private ImportFinder $importFinder
    ) {}

    /**
     * @return EndpointDefinition[]
     */
    public function analyse(Route $route): array
    {
        $fileContent = file_get_contents($route->controllerPath);
        $fileStmts = $this->nodeParser->parse($fileContent);

        $namespace = $this->nodeFinder->findFirstInstanceOf($fileStmts, Namespace_::class);
        if (null === $namespace) {
            return [];
        }

        $imports = $this->importFinder->extract($fileStmts);
        $classes = $this->classFinder->extract($fileStmts);

        return $this->analyseClasses($route, $namespace, $classes, $imports);
    }

    private function analyseClasses(Route $route, Namespace_ $namespace, array $classes, array $imports): array
    {
        $endpoints = [];

        $functionName = $route->functionName;
        foreach ($classes as $class) {
            $function = $this->nodeFinder->findFirst($class, function ($node) use ($functionName) {
                return $node instanceof ClassMethod && $node->name->name == $functionName;
            });

            if ($function != null && $function instanceof ClassMethod) {
                $endpoint = $this->endpointAnalyser->analyse(
                    route: $route,
                    context: new ClassMethodContext(
                        namespace: $namespace,
                        imports: $imports,
                        class: $class,
                        method: $function
                    )
                );

                if (null !== $endpoint) {
                    $this->logEndpointInfo($endpoint);

                    array_push(
                        $endpoints,
                        $endpoint
                    );   
                } else {
                    $this->logger->info("No endpoint");
                }
            }
        }

        return array_filter(
            array: $endpoints,
            callback: function ($enpoint) {
                return $enpoint != null;
            }
        );
    }

    private function logEndpointInfo(EndpointDefinition $endpoint): void
    {
        $this->logger->info("");
        $this->logger->info($endpoint->method . " " . $endpoint->path);

        if (null !== $endpoint->getParameters()) {
            $this->logger->info("Parameters:");
            foreach ($endpoint->getParameters() as $param) {
                $this->logger->info(" - " . $param->toString());
            }
        }

        if (null !== $endpoint->getResponse()) {
            $this->logger->info("Response:");
            foreach ($endpoint->getResponse() as $res) {
                $this->logger->info(" - " . $res->toString());
            }
        }
    }
}
