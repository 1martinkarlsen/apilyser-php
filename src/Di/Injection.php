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
use Apilyser\Extractor\AttributeExtractor;
use Apilyser\Extractor\ClassExtractor;
use Apilyser\Extractor\ClassImportsExtractor;
use Apilyser\Extractor\FileClassesExtractor;
use Apilyser\Extractor\MethodParameterExtractor;
use Apilyser\Extractor\VariableUsageExtractor;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Extractor\MethodPathExtractor;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Parser\Api\SymfonyApiParser;
use Apilyser\Parser\FileParser;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route\RouteStrategy;
use Apilyser\Parser\Route\SymfonyAttributeParser;
use Apilyser\Parser\Route\SymfonyAttributeStrategy;
use Apilyser\Parser\Route\SymfonyYamlRouteStrategy;
use Apilyser\Parser\RouteParser;
use Apilyser\Resolver\ApiFrameworkResolver;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\Node\MethodCallResponseResolver;
use Apilyser\Resolver\Node\NewClassResponseResolver;
use Apilyser\Resolver\ResponseClassUsageResolver;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\RouteResolver;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Resolver\VariableAssignmentFinder;
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

        $this->services[Analyser::class] = new Analyser(
            openApiAnalyser: $this->get(OpenApiAnalyser::class),
            routeResolver: $this->get(RouteResolver::class),
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
        $this->services[OutputInterface::class] = $this->output;
        $this->services[NodeDumper::class] = new NodeDumper();
        $this->services[NodeFinder::class] = new NodeFinder();

        $this->services[MethodPathExtractor::class] = new MethodPathExtractor();

        // Factories
        $this->services[ParameterDefinitionFactory::class] = new ParameterDefinitionFactory();

        // Parser
        $this->services[NodeParser::class] = new NodeParser();
        $this->services[FileParser::class] = new FileParser($this->rootPath . $this->configuration[Configuration::CFG_CODE_PATH]);
        
        // Route parser
        $this->services[AttributeExtractor::class] = new AttributeExtractor();

        // Resolver
        $this->services[NamespaceResolver::class] = new NamespaceResolver(
            output: $this->get(OutputInterface::class),
            rootPath: $this->rootPath
        );
        $this->services[ClassAstResolver::class] = new ClassAstResolver(
            namespaceResolver: $this->get(NamespaceResolver::class),
            nodeParser: $this->get(NodeParser::class)
        );
        $this->services[TypeStructureResolver::class] = new TypeStructureResolver(
            output: $this->get(OutputInterface::class),
            classAstResolver: $this->get(ClassAstResolver::class)
        );

        // Traverser
        $this->services[ClassUsageTraverserFactory::class] = new ClassUsageTraverserFactory(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );

        // Http parsers
        $this->services[SymfonyApiParser::class] = new SymfonyApiParser(
            typeStructureResolver: $this->get(TypeStructureResolver::class)
        );
        $httpDelegate = new HttpDelegate();
        $httpDelegate->registerParser($this->get(SymfonyApiParser::class));
        $this->services[HttpDelegate::class] = $httpDelegate;

        // Extractor
        $this->services[FileClassesExtractor::class] = new FileClassesExtractor(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[ClassExtractor::class] = new ClassExtractor(
            classUsageTraverserFactory: $this->get(ClassUsageTraverserFactory::class)
        );
        $this->services[ClassImportsExtractor::class] = new ClassImportsExtractor(
            nodeFinder: $this->get(NodeFinder::class)
        );
        $this->services[VariableUsageExtractor::class] = new VariableUsageExtractor();
        $this->services[MethodParameterExtractor::class] = new MethodParameterExtractor(
            namespaceResolver: $this->get(NamespaceResolver::class)
        );

        $this->services[VariableAssignmentFinder::class] = new VariableAssignmentFinder();

        $this->services[NewClassResponseResolver::class] = new NewClassResponseResolver(
            namespaceResolver: $this->get(NamespaceResolver::class),
            typeStructureResolver: $this->get(TypeStructureResolver::class),
            httpDelegate: $this->get(HttpDelegate::class),
            variableAssignmentFinder: $this->get(VariableAssignmentFinder::class),
            classAstResolver: $this->get(ClassAstResolver::class)
        );
        $this->services[MethodCallResponseResolver::class] = new MethodCallResponseResolver(
            httpDelegate: $this->get(HttpDelegate::class)
        );
        $this->services[ResponseClassUsageResolver::class] = new ResponseClassUsageResolver(
            classUsageResolvers: [
                $this->get(NewClassResponseResolver::class),
                $this->get(MethodCallResponseResolver::class)
            ]
        );
        $this->services[ResponseResolver::class] = new ResponseResolver(
            classUsageResolver: $this->get(ResponseClassUsageResolver::class)
        );

        $this->services[ApiFrameworkResolver::class] = new ApiFrameworkResolver(
            classExtractor: $this->get(ClassExtractor::class),
            httpDelegate: $this->get(HttpDelegate::class)
        );

        // Rules
        $this->services[ApiComparison::class] = new ApiComparison(
            output: $this->get(OutputInterface::class)
        );

        // Analyzer
        $this->services[MethodAnalyser::class] = new MethodAnalyser(
            methodPathExtractor: $this->get(MethodPathExtractor::class),
            responseResolver: $this->get(ResponseResolver::class),
            httpDelegate: $this->get(HttpDelegate::class),
            classUsageTraverserFactory: $this->get(ClassUsageTraverserFactory::class),
            classAstResolver: $this->get(ClassAstResolver::class)
        );
        $this->services[OpenApiAnalyser::class] = new OpenApiAnalyser(
            openApiDocPath: $this->rootPath . $this->configuration[Configuration::CFG_OPEN_API_PATH]
        );
        $this->services[RequestAnalyser::class] = new RequestAnalyser(
            httpDelegate: $this->get(HttpDelegate::class),
            methodParameterExtractor: $this->get(MethodParameterExtractor::class),
            parameterDefinitionFactory: $this->get(ParameterDefinitionFactory::class)
        );
        $this->services[ResponseAnalyser::class] = new ResponseAnalyser(
            output: $this->get(OutputInterface::class),
            methodAnalyser: $this->get(MethodAnalyser::class)
        );
        $this->services[EndpointAnalyser::class] = new EndpointAnalyser(
            requestAnalyzer: $this->get(RequestAnalyser::class),
            responseAnalyzer: $this->get(ResponseAnalyser::class)
        );
        $this->services[FileAnalyser::class] = new FileAnalyser(
            nodeParser: $this->get(NodeParser::class),
            nodeFinder: $this->get(NodeFinder::class),
            endpointAnalyser: $this->get(EndpointAnalyser::class),
            fileClassesExtractor: $this->get(FileClassesExtractor::class),
            classImportsExtractor: $this->get(ClassImportsExtractor::class)
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
            extractor: $this->get(AttributeExtractor::class)
        );

        $customRouteParserConfig = $this->configuration[Configuration::CFG_CUSTOM_ROUTE_PARSER];
        $parsers = [];
        if (isset($customRouteParserConfig)) {
            foreach ($customRouteParserConfig as $customRouteParser) {
                // TODO: Instantiate class from reflection and add to strategies list

                $instance = new $customRouteParser();
                if ($instance instanceof RouteStrategy) {
                    array_push($parsers, $instance);
                }
            }
        }

        $this->services[RouteResolver::class] = new RouteResolver(
            strategies: [
                new SymfonyYamlRouteStrategy(namespaceResolver: $this->get(NamespaceResolver::class)),
                new SymfonyAttributeStrategy(
                    nodeFinder: $this->get(NodeFinder::class),
                    nodeParser: $this->get(NodeParser::class),
                    fileParser: $this->get(FileParser::class),
                    attributeParser: $this->get(SymfonyAttributeParser::class),
                    fileClassesExtractor: $this->get(FileClassesExtractor::class)
                ),
                ...$parsers
            ]
        );

        $this->services[RouteParser::class] = new RouteParser(
            projectPath: $this->rootPath,
            routeResolver: $this->get(RouteResolver::class)
        );
    }

}