<?php declare(strict_types=1);

namespace Apilyser\Parser\Route;

use Apilyser\Extractor\FileClassesExtractor;
use Apilyser\Parser\FileParser;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use Symfony\Component\Yaml\Yaml;

class SymfonyAttributeStrategy implements RouteStrategy
{

    public function __construct(
        private NodeFinder $nodeFinder,
        private NodeParser $nodeParser,
        private FileParser $fileParser,
        private SymfonyAttributeParser $attributeParser,
        private FileClassesExtractor $fileClassesExtractor
    ) {}

    public function canHandle(string $rootPath): bool
    {
        return file_exists($rootPath . "/config/routes.yaml") ||
            file_exists($rootPath . "/config/routes.yml");
    }

    public function parseRoutes(string $rootPath): array
    {
        $routes = [];

        $supportedFiles = [
            $rootPath . "/config/routes.yaml",
            $rootPath . "/config/routes.yml"
        ];

        foreach ($supportedFiles as $file) {
            if (file_exists($file)) {
                $yamlRoutes = $this->parseYamlRoutes($file);
                array_push($routes, ...$yamlRoutes);
            }
        }

        return $routes;
    }

    private function parseYamlRoutes(string $filePath)
    {
        $routes = [];
        $content = Yaml::parseFile($filePath);

        foreach ($content as $routeName => $routeConfig) {
            if ($routeName === "controllers") {
                array_push(
                    $routes,
                    ...$this->createRouteFromConfig($routeConfig)
                );
            }
        }

        return $routes;
    }

    /**
     * @return Route[]
     */
    private function createRouteFromConfig(array $config): array
    {
        $routes = [];

        if (!isset($config['type']) || $config['type'] != 'attribute') {
            return [];
        }

        // Handle resource imports
        if (!isset($config['resource'])) {
            return [];
        }

        $routesRootPath = null;
        if (is_array($config['resource'])) {
            if (!isset($config['resource']['path'])) {
                return [];
            }

            $routesRootPath = $config['resource']['path'];
        } else {
            $routesRootPath = $config['resource'];
        }

        // Look through all files in path directory
        $files = $this->fileParser->getFiles($routesRootPath);
        
        foreach ($files as $file) {
            $fileContent = file_get_contents($file);
            $fileStmts = $this->nodeParser->parse($fileContent);

            $classes = $this->fileClassesExtractor->extract($fileStmts);

            foreach ($classes as $class) {
                $classFunctions = $this->nodeFinder->findInstanceOf($class, ClassMethod::class);
                $filteredFunctions = array_filter(
                    $classFunctions, 
                    function(ClassMethod $method) {
                        return $method->isPublic();
                    }
                );

                foreach ($filteredFunctions as $method) {
                    if ($method->attrGroups != null) {
                        $route = $this->attributeParser->parse(class: $class, method: $method);
                        if ($route) {
                            $newRoute = new Route(
                                path: $route->path,
                                method: $route->method,
                                controllerPath: $file,
                                functionName: $method->name->name
                            );
                            array_push(
                                $routes,
                                $newRoute
                            );
                        }
                    }
                }
            }
        }

        return $routes;
    }
}