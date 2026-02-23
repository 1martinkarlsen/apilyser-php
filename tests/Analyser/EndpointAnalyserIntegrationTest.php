<?php

namespace Apilyser\tests\Analyser;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Analyser\EndpointAnalyser;
use Apilyser\Analyser\MethodAnalyser;
use Apilyser\Analyser\RequestAnalyser;
use Apilyser\Analyser\ResponseAnalyser;
use Apilyser\Ast\ExecutionPathFinder;
use Apilyser\Ast\MethodParameterFinder;
use Apilyser\Ast\Node\NameHelper;
use Apilyser\Ast\VariableAssignmentFinder;
use Apilyser\Ast\Visitor\ClassUsageVisitorFactory;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Framework\FrameworkRegistry;
use Apilyser\Framework\SymfonyAdapter;
use Apilyser\Parser\NodeParser;
use Apilyser\Parser\Route;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\MethodContextResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\Node\MethodCallResponseResolver;
use Apilyser\Resolver\Node\NewClassResponseResolver;
use Apilyser\Resolver\ResponseBodyResolver;
use Apilyser\Resolver\ResponseClassUsageResolver;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Util\Logger;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class EndpointAnalyserIntegrationTest extends TestCase
{

    private RequestAnalyser $requestAnalyser;
    private ResponseAnalyser $responseAnalyser;
    private EndpointAnalyser $analyser;

    private function findProjectRoot(): string
    {
        $dir = __DIR__;

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        throw new \RuntimeException('Could not find project root (composer.json not found)');
    }

    protected function setUp(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $logger = new Logger($output, true);
        $nodeFinder = new NodeFinder();
        $namespaceResolver = new NamespaceResolver(
            logger: $logger,
            rootPath: $this->findProjectRoot()
        );
        $classAstResolver = new ClassAstResolver(
            namespaceResolver: $namespaceResolver,
            nodeParser: new NodeParser()
        );
        $methodContextResolver = new MethodContextResolver(
            classAstResolver: $classAstResolver,
            nodeFinder: $nodeFinder,
            variableAssignmentFinder: new VariableAssignmentFinder()
        );

        $responseBodyResolver = new ResponseBodyResolver(
            classAstResolver: $classAstResolver,
            methodContextResolver: $methodContextResolver
        );

        $frameworkRegistry = new FrameworkRegistry();
        $frameworkRegistry->registerAdapter(new SymfonyAdapter($responseBodyResolver));

        $this->requestAnalyser = new RequestAnalyser(
            frameworkRegistry: $frameworkRegistry,
            methodParameterFinder: new MethodParameterFinder($namespaceResolver),
            parameterDefinitionFactory: new ParameterDefinitionFactory(),
        );
        $this->responseAnalyser = new ResponseAnalyser(
            methodAnalyser: new MethodAnalyser(
                executionPathFinder: new ExecutionPathFinder(),
                responseResolver: new ResponseResolver(
                    logger: $logger,
                    classUsageResolver: new ResponseClassUsageResolver(
                        classUsageResolvers: [
                            new NewClassResponseResolver(
                                namespaceResolver: $namespaceResolver,
                                responseBodyResolver: $responseBodyResolver,
                                frameworkRegistry: $frameworkRegistry,
                                variableAssignmentFinder: new VariableAssignmentFinder(),
                                classAstResolver: $classAstResolver
                            ),
                            new MethodCallResponseResolver(frameworkRegistry: $frameworkRegistry)
                        ]
                    )
                ),
                frameworkRegistry: $frameworkRegistry,
                classUsageVisitorFactory: new ClassUsageVisitorFactory($namespaceResolver),
                classAstResolver: $classAstResolver
            )
        );

        $this->analyser = new EndpointAnalyser(
            requestAnalyser: $this->requestAnalyser,
            responseAnalyser: $this->responseAnalyser,
        );
    }

    private function parseDataClassMethod(string $methodName): ClassMethodContext
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $dir = __DIR__ . '/../Data/EndpointAnalyserIntegrationData.php';
        $code = file_get_contents($dir);
        $ast = $parser->parse($code);

        $namespaceNode = null;
        $classNode = null;
        $methodNode = null;
        $imports = [];

        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                $namespaceNode = $node;

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $alias = $use->alias ? NameHelper::getName($use->name) : $use->name->getLast();
                            $imports[$alias] = NameHelper::getName($use->name);
                        }
                    }

                    if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
                        $classNode = $stmt;

                        foreach ($stmt->stmts as $classStmt) {
                            if ($classStmt instanceof \PhpParser\Node\Stmt\ClassMethod
                                && $classStmt->name->toString() === $methodName) {
                                $methodNode = $classStmt;
                                break 3;
                            }
                        }
                    }
                }
            }
        }

        if (!$namespaceNode || !$classNode || !$methodNode) {
            throw new \RuntimeException("Could not parse method: $methodName");
        }

        return new ClassMethodContext(
            namespace: $namespaceNode,
            imports: $imports,
            class: $classNode,
            method: $methodNode
        );
    }

    public function testExample()
    {
        $context = $this->parseDataClassMethod("testExample");
        $result = $this->analyser->analyse(
            route: new Route(
                method: "GET",
                path: "/test",
                controllerPath: __DIR__ . '/../Data/ResponseAnalyserIntegrationData.php',
                functionName: "testExample"
            ),
            context: $context
        );

        $parameters = $result->getParameters();
        $responses = $result->getResponse();

        $this->assertNotNull($result);
        $this->assertNotNull($parameters);
        $this->assertNotNull($responses);

        $this->assertCount(expectedCount: 3, haystack: $parameters);
        $this->assertCount(expectedCount: 3, haystack: $responses);
    }
}