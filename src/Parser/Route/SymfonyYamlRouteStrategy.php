<?php declare(strict_types=1);

namespace Apilyser\Parser\Route;

use Apilyser\Parser\Route;
use Apilyser\Resolver\NamespaceResolver;
use Symfony\Component\Yaml\Yaml;

class SymfonyYamlRouteStrategy implements RouteStrategy
{

    public function __construct(
        private NamespaceResolver $namespaceResolver
    ) {}

    public function canHandle(string $rootPath): bool
    {
        return file_exists($rootPath . "/config/routes.yaml") ||
            file_exists($rootPath . "/config/routes.yml");
            //file_exists($rootPath . "/config/routes.xml") ||
            //file_exists($rootPath . "/config/routes/attributes.yaml");
    }

    /**
     * @return Route[]
     */
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

    /**
     * @return Route[]
     */
    private function parseYamlRoutes(string $filePath)
    {
        $routes = [];
        $content = Yaml::parseFile($filePath);

        foreach ($content as $routeName => $routeConfig) {
            array_push(
                $routes,
                ...$this->createRouteFromConfig($routeConfig)
            );
        }

        return $routes;
    }


    /**
     * @return Route[]
     */
    private function createRouteFromConfig(array $config): array
    {
        $routes = [];
        
        if (!isset($config['path']) || !isset($config['controller'])) {
            return [];
        }
        
        $path = $config['path'];
        $controllerCfg = explode("::", $config['controller']);
        $methods = $config['methods'] ?? ['GET'];
        $methods = is_array($methods) ? $methods : [$methods];

        $controllerPath = $this->namespaceResolver->resolveNamespace($controllerCfg[0]);
        $functionName = $controllerCfg[1];

        foreach ($methods as $method) {
            $method = strtoupper($method);
            array_push(
                $routes,
                new Route(
                    path: $path,
                    method: $method,
                    controllerPath: $controllerPath,
                    functionName: $functionName
                )
            );
        }

        return $routes;
    }
}