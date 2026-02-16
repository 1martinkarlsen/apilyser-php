<?php declare(strict_types=1);

namespace Apilyser\Di;

use Apilyser\Analyser\Analyser;
use Apilyser\Analyser\EndpointAnalyser;
use Apilyser\Analyser\FileAnalyser;
use Apilyser\Analyser\MethodAnalyser;
use Apilyser\Analyser\OpenApiAnalyser;
use Apilyser\Analyser\RequestAnalyser;
use Apilyser\Analyser\ResponseAnalyser;
use Apilyser\ApiValidator;
use Apilyser\Comparison\ApiComparison;
use Apilyser\Configuration\Configuration;
use Apilyser\Configuration\ConfigurationLoader;
use Apilyser\Ast\AttributeFinder;
use Apilyser\Ast\ClassUsageFinder;
use Apilyser\Ast\ImportFinder;
use Apilyser\Ast\ClassFinder;
use Apilyser\Ast\MethodParameterFinder;
use Apilyser\Ast\VariableUsageFinder;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Ast\ExecutionPathFinder;
use Apilyser\Framework\FrameworkRegistry;
use Apilyser\Framework\SymfonyAdapter;
use Apilyser\Parser\FileScanner;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route\RouteStrategy;
use Apilyser\Parser\Route\SymfonyAttributeParser;
use Apilyser\Parser\Route\SymfonyAttributeStrategy;
use Apilyser\Parser\Route\SymfonyYamlRouteStrategy;
use Apilyser\Ast\FrameworkClassFinder;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\Node\MethodCallResponseResolver;
use Apilyser\Resolver\Node\NewClassResponseResolver;
use Apilyser\Resolver\ResponseClassUsageResolver;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\RouteCollector;
use Apilyser\Resolver\MethodContextResolver;
use Apilyser\Resolver\ResponseBodyResolver;
use Apilyser\Ast\VariableAssignmentFinder;
use Apilyser\Ast\Visitor\ClassUsageVisitorFactory;
use Apilyser\Util\Logger;
use Exception;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;

class Injection
{

    private array $configuration = [];
    private array $services = [];

    public function __construct(
        public Logger $logger,
        public string $rootPath
    )
    {
        $this->configure();
        $this->setup();
        $this->setupRouting();

        $this->services[Analyser::class] = new Analyser(
            logger: $this->get(Logger::class),
            openApiAnalyser: $this->get(OpenApiAnalyser::class),
            routeCollector: $this->get(RouteCollector::class),
            fileAnalyser: $this->get(FileAnalyser::class),
            comparison: $this->get(ApiComparison::class)
        );
    }

    /**
     * Creates the ApiValidator
     *
     * @return ApiValidator
     */
    public function createApiValidator(): ApiValidator
    {
        return new ApiValidator(
            folderPath: $this->rootPath,
            logger: $this->get(Logger::class),
            analyser: $this->get(Analyser::class)
        );
    }

    /**
     * Get a service from service container.
     *
     * @param string $serviceId
     * @return object
     *
     * @throws Exception
     */
    public function get(string $serviceId): object
    {
        if (isset($this->services[$serviceId])) {
            return $this->services[$serviceId];
        }

        throw new Exception("Class " . $serviceId . " was not found");
    }

    /**
     * Responsible for loading and setting up configuration.
     *
     */
    private function configure(): void
    {
        $configLoader = new ConfigurationLoader();
        $this->configuration = $configLoader->loadFromFile($this->rootPath . "/" . Configuration::CONFIG_PATH);
    }

    /**
     * Responsible for setting up basic classes.
     */
    private function setup()
    {
        $this->services[Logger::class] = $this->logger;
        $this->services[NodeDumper::class] = new NodeDumper();
        $this->services[NodeFinder::class] = new NodeFinder();

        $this->services[ExecutionPathFinder::class] = new ExecutionPathFinder();

        // Factories
        $this->services[ParameterDefinitionFactory::class] = new ParameterDefinitionFactory();

        // Parser
        $this->services[NodeParser::class] = new NodeParser();
        $this->services[FileScanner::class] = new FileScanner($this->rootPath . $this->configuration[Configuration::CFG_CODE_PATH]);

        // Route parser
        $this->services[AttributeFinder::class] = new AttributeFinder();

        // Resolver
        $this->services[NamespaceResolver::class] = new NamespaceResolver(
            logger: $this->get(Logger::class),
            rootPath: $this->rootPath
        );
        $this->services[ClassAstResolver::class] = new ClassAstResolver(
            namespaceResolver: $this->get(NamespaceResolver::class),
            nodeParser: $this->get(NodeParser::class)
        );
        $this->services[MethodContextResolver::class] = new MethodContextResolver(
            classAstResolver: $this->get(ClassAstResolver::class),
            nodeFinder: $this->get(NodeFinder::class),
            variableAssignmentFinder: new VariableAssignmentFinder()
        );
        $this->services[ResponseBodyResolver::class] = new ResponseBodyResolver(
            classAstResolver: $this->get(ClassAstResolver::class),
            methodContextResolver: $this->get(MethodContextResolver::class)
        );

        // Ast Visitors
        $this->services[ClassUsageVisitorFactory::class] = new ClassUsageVisitorFactory(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );

        // Framework adapters
        $this->services[SymfonyAdapter::class] = new SymfonyAdapter(
            responseBodyResolver: $this->get(ResponseBodyResolver::class)
        );
        $frameworkRegistry = new FrameworkRegistry();
        $frameworkRegistry->registerAdapter($this->get(SymfonyAdapter::class));
        $this->services[FrameworkRegistry::class] = $frameworkRegistry;

        // Ast Finders
        $this->services[ClassFinder::class] = new ClassFinder(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[ClassUsageFinder::class] = new ClassUsageFinder(
            classUsageVisitorFactory: $this->get(ClassUsageVisitorFactory::class)
        );
        $this->services[ImportFinder::class] = new ImportFinder(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[VariableUsageFinder::class] = new VariableUsageFinder();
        $this->services[MethodParameterFinder::class] = new MethodParameterFinder(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );

        $this->services[VariableAssignmentFinder::class] = new VariableAssignmentFinder();

        $this->services[NewClassResponseResolver::class] = new NewClassResponseResolver(
            namespaceResolver: $this->get(NamespaceResolver::class),
            responseBodyResolver: $this->get(ResponseBodyResolver::class),
            frameworkRegistry: $this->get(FrameworkRegistry::class),
            variableAssignmentFinder: $this->get(VariableAssignmentFinder::class),
            classAstResolver: $this->get(ClassAstResolver::class)
        );
        $this->services[MethodCallResponseResolver::class] = new MethodCallResponseResolver(
            frameworkRegistry: $this->get(FrameworkRegistry::class)
        );
        $this->services[ResponseClassUsageResolver::class] = new ResponseClassUsageResolver(
            classUsageResolvers: [
                $this->get(NewClassResponseResolver::class),
                $this->get(MethodCallResponseResolver::class)
            ]
        );
        $this->services[ResponseResolver::class] = new ResponseResolver(
            logger: $this->get(Logger::class),
            classUsageResolver: $this->get(ResponseClassUsageResolver::class)
        );

        $this->services[FrameworkClassFinder::class] = new FrameworkClassFinder(
            classUsageFinder: $this->get(ClassUsageFinder::class),
            frameworkRegistry: $this->get(FrameworkRegistry::class)
        );

        // Rules
        $this->services[ApiComparison::class] = new ApiComparison();

        // Analyzer
        $this->services[MethodAnalyser::class] = new MethodAnalyser(
            logger: $this->get(Logger::class),
            executionPathFinder: $this->get(ExecutionPathFinder::class),
            responseResolver: $this->get(ResponseResolver::class),
            frameworkRegistry: $this->get(FrameworkRegistry::class),
            classUsageVisitorFactory: $this->get(ClassUsageVisitorFactory::class),
            classAstResolver: $this->get(ClassAstResolver::class),
        );
        $this->services[OpenApiAnalyser::class] = new OpenApiAnalyser(
            openApiDocPath: $this->rootPath . $this->configuration[Configuration::CFG_OPEN_API_PATH]
        );
        $this->services[RequestAnalyser::class] = new RequestAnalyser(
            frameworkRegistry: $this->get(FrameworkRegistry::class),
            methodParameterFinder: $this->get(MethodParameterFinder::class),
            parameterDefinitionFactory: $this->get(ParameterDefinitionFactory::class)
        );
        $this->services[ResponseAnalyser::class] = new ResponseAnalyser(
            methodAnalyser: $this->get(MethodAnalyser::class)
        );
        $this->services[EndpointAnalyser::class] = new EndpointAnalyser(
            requestAnalyser: $this->get(RequestAnalyser::class),
            responseAnalyser: $this->get(ResponseAnalyser::class)
        );
        $this->services[FileAnalyser::class] = new FileAnalyser(
            logger: $this->get(Logger::class),
            nodeParser: $this->get(NodeParser::class),
            nodeFinder: $this->get(NodeFinder::class),
            endpointAnalyser: $this->get(EndpointAnalyser::class),
            classFinder: $this->get(ClassFinder::class),
            importFinder: $this->get(ImportFinder::class)
        );
    }

    /**
     * Responsible for setting up routing.
     * This setup is seperated from the basic setup as the routing comes from different frameworks.
     *
     * It's important to setup basic classes before setting up routing, as the basic classes might be used
     * by the routing.
     */
    private function setupRouting(): void
    {
        $this->services[SymfonyAttributeParser::class] = new SymfonyAttributeParser(
            extractor: $this->get(AttributeFinder::class)
        );

        $customRouteParserConfig = $this->configuration[Configuration::CFG_CUSTOM_ROUTE_PARSER];
        $parsers = [];
        if (isset($customRouteParserConfig)) {
            foreach ($customRouteParserConfig as $customRouteParser) {
                $instance = new $customRouteParser();
                if ($instance instanceof RouteStrategy) {
                    array_push($parsers, $instance);
                }
            }
        }

        $this->services[RouteCollector::class] = new RouteCollector(
            strategies: [
                new SymfonyYamlRouteStrategy(namespaceResolver: $this->get(NamespaceResolver::class)),
                new SymfonyAttributeStrategy(
                    nodeFinder: $this->get(NodeFinder::class),
                    nodeParser: $this->get(NodeParser::class),
                    fileScanner: $this->get(FileScanner::class),
                    attributeParser: $this->get(SymfonyAttributeParser::class),
                    classFinder: $this->get(ClassFinder::class)
                ),
                ...$parsers
            ]
        );
    }

}
