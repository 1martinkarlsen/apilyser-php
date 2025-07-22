<?php

namespace Apilyser\Di;

use Apilyser\Analyser\Analyser;
use Apilyser\Analyser\EndpointAnalyser;
use Apilyser\Analyser\FileAnalyser;
use Apilyser\Analyser\OpenApiAnalyser;
use Apilyser\Analyser\RequestAnalyser;
use Apilyser\Analyser\ResponseAnalyser;
use Apilyser\ApiValidator;
use Apilyser\Comparison\ApiComparison;
use Apilyser\Configuration\Configuration;
use Apilyser\Configuration\ConfigurationLoader;
use Apilyser\Extractor\AttributeExtractor;
use Apilyser\Extractor\ClassExtractor;
use Apilyser\Extractor\ClassImportsExtractor;
use Apilyser\Extractor\FileClassesExtractor;
use Apilyser\Extractor\MethodParameterExtractor;
use Apilyser\Extractor\MethodStructureExtractor;
use Apilyser\Extractor\VariableUsageExtractor;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Parser\Api\SymfonyApiParser;
use Apilyser\Parser\FileParser;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route\SymfonyAttributeParser;
use Apilyser\Parser\Route\SymfonyAttributeStrategy;
use Apilyser\Parser\Route\SymfonyYamlRouteStrategy;
use Apilyser\Parser\RouteParser;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\Node\MethodCallResponseResolver;
use Apilyser\Resolver\Node\NewClassResponseResolver;
use Apilyser\Resolver\ResponseClassUsageResolver;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\RouteResolver;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use Exception;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use Symfony\Component\Console\Output\OutputInterface;

class Injection
{

    private array $configuration = [];
    private array $services = [];

    public function __construct(
        public OutputInterface $output,
        public string $rootPath
    )
    {
        $this->configure();
        $this->setup();
        $this->setupRouting();
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
            output: $this->get(OutputInterface::class),
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
            return $this->services[$serviceId]();
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
        $cfg = $configLoader->loadFromFile($this->rootPath . "/" . Configuration::CONFIG_PATH);
        $this->configuration = $cfg;
    }

    /**
     * Responsible for setting up basic classes.
     */
    private function setup()
    {
        $this->services[OutputInterface::class] = fn() => $this->output;
        $this->services[NodeDumper::class] = fn() => new NodeDumper();
        $this->services[NodeFinder::class] = fn() => new NodeFinder();

        // Factories
        $this->services[ParameterDefinitionFactory::class] = fn() => new ParameterDefinitionFactory();

        // Parser
        $this->services[NodeParser::class] = fn() => new NodeParser();
        $this->services[FileParser::class] = fn() => new FileParser($this->rootPath . $this->configuration[Configuration::CFG_CODE_PATH]);
        

        // Route parser
        $this->services[AttributeExtractor::class] = fn() => new AttributeExtractor();

        // Resolver
        $this->services[NamespaceResolver::class] = fn() => new NamespaceResolver(
            output: $this->get(OutputInterface::class),
            rootPath: $this->rootPath
        );
        $this->services[ClassAstResolver::class] = fn() => new ClassAstResolver(
            namespaceResolver: $this->get(NamespaceResolver::class),
            nodeParser: $this->get(NodeParser::class)
        );
        $this->services[TypeStructureResolver::class] = fn() => new TypeStructureResolver(
            output: $this->get(OutputInterface::class),
            nodeDumper: $this->get(NodeDumper::class),
            namespaceResolver: $this->get(NamespaceResolver::class),
            nodeParser: $this->get(NodeParser::class),
            classAstResolver: $this->get(ClassAstResolver::class)
        );

        // Traverser
        $this->services[ClassUsageTraverserFactory::class] = fn() => new ClassUsageTraverserFactory(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );

        // Http parsers
        $this->services[SymfonyApiParser::class] = fn() => new SymfonyApiParser(
            output: $this->get(OutputInterface::class),
            namespaceResolver: $this->get(NamespaceResolver::class),
            typeStructureResolver: $this->get(TypeStructureResolver::class)
        );
        $httpDelegate = new HttpDelegate();
        $httpDelegate->registerParser($this->get(SymfonyApiParser::class));
        $this->services[HttpDelegate::class] = fn() => $httpDelegate;

        // Extractor
        $this->services[FileClassesExtractor::class] = fn() => new FileClassesExtractor(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[ClassExtractor::class] = fn() => new ClassExtractor(
            classUsageTraverserFactory: $this->get(ClassUsageTraverserFactory::class),
            httpDelegate: $this->get(HttpDelegate::class)
        );
        $this->services[ClassImportsExtractor::class] = fn() => new ClassImportsExtractor(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[VariableUsageExtractor::class] = fn() => new VariableUsageExtractor();
        $this->services[MethodParameterExtractor::class] = fn() => new MethodParameterExtractor(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );
        $this->services[MethodStructureExtractor::class] = fn() => new MethodStructureExtractor();

        $this->services[NewClassResponseResolver::class] = fn() => new NewClassResponseResolver(
            output: $this->get(OutputInterface::class),
            nodeParser: $this->get(NodeParser::class),
            namespaceResolver: $this->get(NamespaceResolver::class),
            typeStructureResolver: $this->get(TypeStructureResolver::class),
            httpDelegate: $this->get(HttpDelegate::class)
        );
        $this->services[MethodCallResponseResolver::class] = fn() => new MethodCallResponseResolver(
            output: $this->get(OutputInterface::class),
            httpDelegate: $this->get(HttpDelegate::class)
        );
        $this->services[ResponseClassUsageResolver::class] = fn() => new ResponseClassUsageResolver(
            classUsageResolvers: [
                $this->get(NewClassResponseResolver::class),
                $this->get(MethodCallResponseResolver::class)
            ]
        );
        $this->services[ResponseResolver::class] = fn() => new ResponseResolver(
            output: $this->get(OutputInterface::class),
            variableUsageExtractor: $this->get(VariableUsageExtractor::class),
            classUsageResolver: $this->get(ResponseClassUsageResolver::class)
        );

        // Rules
        $this->services[ApiComparison::class] = fn() => new ApiComparison(
            output: $this->get(OutputInterface::class)
        );

        // Analyzer
        $this->services[OpenApiAnalyser::class] = fn() => new OpenApiAnalyser(
            openApiDocPath: $this->rootPath . $this->configuration[Configuration::CFG_OPEN_API_PATH]
        );
        $this->services[RequestAnalyser::class] = fn() => new RequestAnalyser(
            httpDelegate: $this->get(HttpDelegate::class),
            methodParameterExtractor: $this->get(MethodParameterExtractor::class),
            parameterDefinitionFactory: $this->get(ParameterDefinitionFactory::class)
        );
        $this->services[ResponseAnalyser::class] = fn() => new ResponseAnalyser(
            classExtractor: $this->get(ClassExtractor::class),
            methodStructureExtractor: $this->get(MethodStructureExtractor::class),
            variableUsageExtractor: $this->get(VariableUsageExtractor::class),
            responseResolver: $this->get(ResponseResolver::class),
            dumper: $this->get(NodeDumper::class)
        );
        $this->services[EndpointAnalyser::class] = fn() => new EndpointAnalyser(
            requestAnalyzer: $this->get(RequestAnalyser::class),
            responseAnalyzer: $this->get(ResponseAnalyser::class)
        );
        $this->services[FileAnalyser::class] = fn() => new FileAnalyser(
            nodeParser: $this->get(NodeParser::class),
            nodeFinder: $this->get(NodeFinder::class),
            endpointAnalyser: $this->get(EndpointAnalyser::class),
            fileClassesExtractor: $this->get(FileClassesExtractor::class),
            classImportsExtractor: $this->get(ClassImportsExtractor::class)
        );
        $this->services[Analyser::class] = fn() => new Analyser(
            output: $this->get(OutputInterface::class),
            openApiAnalyser: $this->get(OpenApiAnalyser::class),
            routeResolver: $this->get(RouteResolver::class),
            fileAnalyser: $this->get(FileAnalyser::class),
            comparison: $this->get(ApiComparison::class)
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
        $this->services[SymfonyAttributeParser::class] = fn() => new SymfonyAttributeParser(
            extractor: $this->get(AttributeExtractor::class)
        );

        $this->services[RouteResolver::class] = fn() => new RouteResolver(
            strategies: [
                new SymfonyYamlRouteStrategy(namespaceResolver: $this->get(NamespaceResolver::class)),
                new SymfonyAttributeStrategy(
                    nodeFinder: $this->get(NodeFinder::class),
                    nodeParser: $this->get(NodeParser::class),
                    fileParser: $this->get(FileParser::class),
                    attributeParser: $this->get(SymfonyAttributeParser::class),
                    fileClassesExtractor: $this->get(FileClassesExtractor::class)
                )
            ]
        );

        $this->services[RouteParser::class] = fn() => new RouteParser(
            projectPath: $this->rootPath,
            routeResolver: $this->get(RouteResolver::class)
        );
    }

}