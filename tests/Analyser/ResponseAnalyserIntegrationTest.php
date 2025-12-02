<?php

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Analyser\MethodAnalyser;
use Apilyser\Analyser\ResponseAnalyser;
use Apilyser\Extractor\MethodPathExtractor;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Parser\Api\SymfonyApiParser;
use Apilyser\Parser\NodeParser;
use Apilyser\Resolver\ClassAstResolver;
use Apilyser\Resolver\NamespaceResolver;
use Apilyser\Resolver\Node\MethodCallResponseResolver;
use Apilyser\Resolver\Node\NewClassResponseResolver;
use Apilyser\Resolver\ResponseClassUsageResolver;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseAnalyserIntegrationTest extends TestCase
{
    private OutputInterface $output;
    private NamespaceResolver $namespaceResolver;
    private ClassAstResolver $classAstResolver;
    private TypeStructureResolver $typeStructureResolver;
    private HttpDelegate $httpDelegate;
    private ResponseAnalyser $analyser;
    
    protected function setUp(): void
    {
        $this->output = $this->createMock(OutputInterface::class);
        $this->namespaceResolver = new NamespaceResolver(
            output: $this->output,
            rootPath: ""
        );
        $this->classAstResolver = new ClassAstResolver(
            namespaceResolver: $this->namespaceResolver,
            nodeParser: new NodeParser()
        );
        $this->typeStructureResolver = new TypeStructureResolver(
            output: $this->output,
            classAstResolver: $this->classAstResolver
        );
        $this->httpDelegate = new HttpDelegate();
        $this->httpDelegate->registerParser(new SymfonyApiParser($this->typeStructureResolver));

        $this->analyser = new ResponseAnalyser(
            output: $this->output,
            methodAnalyser: new MethodAnalyser(
                methodPathExtractor: new MethodPathExtractor(),
                responseResolver: new ResponseResolver(
                    new ResponseClassUsageResolver(
                        classUsageResolvers: [
                            new NewClassResponseResolver(
                                namespaceResolver: $this->namespaceResolver,
                                typeStructureResolver: $this->typeStructureResolver,
                                httpDelegate: $this->httpDelegate
                            ),
                            new MethodCallResponseResolver(httpDelegate: $this->httpDelegate)
                        ]
                    )
                ),
                httpDelegate: $this->httpDelegate,
                classUsageTraverserFactory: new ClassUsageTraverserFactory($this->namespaceResolver),
                classAstResolver: $this->classAstResolver,
                typeStructureResolver: $this->typeStructureResolver
            )
        );
    }

    private function parseDataClassMethod(string $methodName): ClassMethodContext
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $dir = __DIR__ . '/../data/ResponseAnalyserIntegrationData.php';
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
                            $alias = $use->alias ? $use->name->toString() : $use->name->getLast();
                            $imports[$alias] = $use->name->name;
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

    // Testing for finding responses where response is directly returned.
    public function testFindWithOneDirectReturn()
    {   
        $context = $this->parseDataClassMethod("withOneDirectReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 1, haystack: $result);

        $first = $result[0];
        $this->assertEquals(expected: 200, actual: $first->statusCode);
    }

    // Testing for finding responses where there could be multiple direct responses.
    public function testFindWithMultipleDirectReturn()
    {
        $context = $this->parseDataClassMethod("withMultipleDirectReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 2, haystack: $result);
    }

    public function testFindWithVariableReturn()
    {
        $context = $this->parseDataClassMethod("withVariableReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 1, haystack: $result);
    }

    public function testFindWithOuterScopeVariableReturn()
    {
        $context = $this->parseDataClassMethod("withOuterScopeVariableReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 2, haystack: $result);
    }

    public function testFindWithMethodCallReturn()
    {
        $context = $this->parseDataClassMethod("withMethodCallReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 1, haystack: $result);
    }

    public function testFindWithServiceCallReturn()
    {
        $context = $this->parseDataClassMethod("withServiceCallReturn");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 1, haystack: $result);
    }

    public function testFindWithVariableStatusCode()
    {
        $context = $this->parseDataClassMethod("withVariableStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 401, actual: $first->statusCode);
    }

    public function testFindWithConstantStatusCode()
    {
        $context = $this->parseDataClassMethod("withConstantStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 401, actual: $first->statusCode);
    }

    public function testFindWithVariableParameterStatusCode()
    {
        $context = $this->parseDataClassMethod("withParameterVariableStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 200, actual: $first->statusCode);
    }

    public function testFindWithMethodCallStatusCode()
    {
        $context = $this->parseDataClassMethod("withMethodCallStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 200, actual: $first->statusCode);
    }

    public function testFindWithDefaultStatusCode()
    {
        $context = $this->parseDataClassMethod("withDefaultStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 200, actual: $first->statusCode);
    }

    public function testFindWithDirectArrayBody()
    {
        $context = $this->parseDataClassMethod("withDirectArrayBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertNotEmpty($body);
        $this->assertNotNull($body[0]);

        $firstBodyItem = $body[0];

        $this->assertEquals(expected: "id", actual: $firstBodyItem->getName());
        $this->assertEquals(expected: "int", actual: $firstBodyItem->getType());
        $this->assertEquals(expected: false, actual: $firstBodyItem->getIsNullable());
    }

    public function testFindWithDirectNullBody()
    {
        $context = $this->parseDataClassMethod("withDirectNullBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertEmpty($body);
    }

    public function testFindWithDirectEmptyArrayBody()
    {
        $context = $this->parseDataClassMethod("withDirectEmptyArrayBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertEmpty($body);
    }

    public function testFindWithDirectArrayWithVariableBody()
    {
        $context = $this->parseDataClassMethod("withDirectArrayWithVariablesBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertNotEmpty($body);

        // Testing local variable
        $first = $body[0];
        $this->assertNotNull($first);
        $this->assertEquals(expected: "id", actual: $first->getName());
        $this->assertEquals(expected: "int", actual: $first->getType());
        $this->assertEquals(expected: false, actual: $first->getIsNullable());

        // Testing service method to receive property
        $second = $body[1];
        $this->assertNotNull($second);
        $this->assertEquals(expected: "name", actual: $second->getName());
        $this->assertEquals(expected: "string", actual: $second->getType());
        $this->assertEquals(expected: false, actual: $second->getIsNullable());

        // Testing local function to return property
        $third = $body[1];
        $this->assertNotNull($third);
        $this->assertEquals(expected: "email", actual: $third->getName());
        $this->assertEquals(expected: "string", actual: $third->getType());
        $this->assertEquals(expected: false, actual: $third->getIsNullable());
    }

    public function testFindWithVariableArrayBody()
    {
        $context = $this->parseDataClassMethod("withVariableArrayBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertNotEmpty($body);

        // Testing local variable
        $first = $body[0];
        $this->assertNotNull($first);
        $this->assertEquals(expected: "id", actual: $first->getName());
        $this->assertEquals(expected: "int", actual: $first->getType());
        $this->assertEquals(expected: false, actual: $first->getIsNullable());
    }

    public function testFindWithMethodCallBody()
    {
        $context = $this->parseDataClassMethod("withMethodCallBody");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];
        $body = $first->structure;

        $this->assertNotEmpty($body);

        // Testing local variable
        $first = $body[0];
        $this->assertNotNull($first);
        $this->assertEquals(expected: "id", actual: $first->getName());
        $this->assertEquals(expected: "int", actual: $first->getType());
        $this->assertEquals(expected: false, actual: $first->getIsNullable());
    }
}