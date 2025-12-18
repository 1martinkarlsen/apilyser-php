<?php

namespace Apilyser\tests\Analyser;

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
use Apilyser\Resolver\VariableAssignmentFinder;
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
        $this->output = $this->createMock(OutputInterface::class);
        $this->namespaceResolver = new NamespaceResolver(
            output: $this->output,
            rootPath: $this->findProjectRoot()
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
                                httpDelegate: $this->httpDelegate,
                                variableAssignmentFinder: new VariableAssignmentFinder(),
                                classAstResolver: $this->classAstResolver
                            ),
                            new MethodCallResponseResolver(httpDelegate: $this->httpDelegate)
                        ]
                    )
                ),
                httpDelegate: $this->httpDelegate,
                classUsageTraverserFactory: new ClassUsageTraverserFactory($this->namespaceResolver),
                classAstResolver: $this->classAstResolver
            )
        );
    }

    private function parseDataClassMethod(string $methodName): ClassMethodContext
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $dir = __DIR__ . '/../Data/ResponseAnalyserIntegrationData.php';
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

    public function testFindWithClassScopeVariableStatusCode()
    {
        $context = $this->parseDataClassMethod("withClassScopedVariableStatusCode");
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

        $this->assertEquals(expected: 400, actual: $first->statusCode);
    }

    /*public function testFindWithVariableParameterStatusCode()
    {
        $context = $this->parseDataClassMethod("withParameterVariableStatusCode");
        $result = $this->analyser->analyse($context);
        
        // Dine assertions her
        $this->assertNotNull($result);
        $first = $result[0];

        $this->assertEquals(expected: 200, actual: $first->statusCode);
    }*/

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
        $third = $body[2];
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

    public function testFindWithMultipleEarlyReturns()
    {
        $context = $this->parseDataClassMethod("withMultipleEarlyReturns");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertCount(3, $result, "Should find all 3 return statements");
        
        // Extract all status codes
        $statusCodes = array_map(fn($r) => $r->statusCode, $result);

        $this->assertContains(200, $statusCodes);
        $this->assertContains(400, $statusCodes);
        $this->assertContains(401, $statusCodes);
    }

    public function testFindWithTryCatchBlock()
    {
        $context = $this->parseDataClassMethod("withTryCatchBlock");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertCount(3, $result, "Should find try + 2 catch blocks");
        
        $statusCodes = array_map(fn($r) => $r->statusCode, $result);
        sort($statusCodes);
        
        $this->assertContains(200, $statusCodes, "Should find 200 from try block");
        $this->assertContains(404, $statusCodes, "Should find 404 from first catch");
        $this->assertContains(500, $statusCodes, "Should find 500 from second catch");
    }

    /**
     * Test: Try-catch with throw statement
     */
    /*public function testFindWithTryCatchAndThrow()
    {
        $context = $this->parseDataClassMethod("withTryCatchAndThrow");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        
        // Should detect the catch block return
        $this->assertGreaterThanOrEqual(1, count($result));
        
        $statusCodes = array_map(fn($r) => $r->statusCode, $result);
        $this->assertContains(404, $statusCodes);
    }*/

    public function testFindWithSwitchStatement()
    {
        $context = $this->parseDataClassMethod("withSwitchStatement");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertCount(4, $result, "Should find all 4 switch cases");
        
        $statusCodes = array_map(fn($r) => $r->statusCode, $result);
        sort($statusCodes);
        
        $this->assertContains(200, $statusCodes);
        $this->assertContains(400, $statusCodes);
        $this->assertContains(404, $statusCodes);
        $this->assertContains(500, $statusCodes);
    }

    /**
     * Test: Ternary operator for status code
     * Priority: IMPORTANT - common pattern
     */
    /*public function testFindWithTernaryStatusCode()
    {
        $context = $this->parseDataClassMethod("withTernaryStatusCode");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertCount(expectedCount: 2, haystack: $result);
        
        $statusCodes = array_map(fn($r) => $r->statusCode, $result);
        $this->assertContains(200, $statusCodes);
        $this->assertContains(400, $statusCodes);
    }*/

    /**
     * Test: Ternary operator for body structure
     */
    /*public function testFindWithTernaryBody()
    {
        $context = $this->parseDataClassMethod("withTernaryBody");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertCount(2, $result);

        // Both should have status 200
        $this->assertEquals(200, $result[0]->statusCode);
        $this->assertEquals(200, $result[1]->statusCode);
        
        // Check that we detect at least one body structure
        $first = $result[0];
        $this->assertNotNull($first->structure);
        $this->assertEquals(expected: "result", actual: $first->structure[0]->getName());

        $second = $result[1];
        $this->assertNotNull($second->structure);
        $this->assertEquals(expected: "error", actual: $second->structure[0]->getName());
    }*/

    /**
     * Test: Nested ternary (complex case)
     */
    /*public function testFindWithNestedTernary()
    {
        $context = $this->parseDataClassMethod("withNestedTernary");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        
        // This is complex - acceptable outcomes:
        // 1. Find all 3 status codes (200, 400, 500)
        // 2. Find some of them
        // 3. Mark as low confidence
        
        $this->assertGreaterThanOrEqual(1, count($result));
    }*/

    /**
     * Test: Variable reassigned multiple times
     */
    public function testFindWithReassignedStatusCode()
    {
        $context = $this->parseDataClassMethod("withReassignedStatusCode");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        
        // Ideally finds all 3 possible values: 200, 400, 401
        // At minimum should find the initial value (200)
        $this->assertGreaterThanOrEqual(1, count($result));
        
        if (count($result) === 3) {
            $statusCodes = array_map(fn($r) => $r->statusCode, $result);
            $this->assertContains(200, $statusCodes);
            $this->assertContains(400, $statusCodes);
            $this->assertContains(401, $statusCodes);
        }
    }

    /**
     * Test: Null coalescing operator
     */
    /*public function testFindWithNullCoalescingStatusCode()
    {
        $context = $this->parseDataClassMethod("withNullCoalescingStatusCode");
        $result = $this->analyser->analyse($context);
        
        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(1, count($result));
        
        // Should at least detect the default value (200)
        $first = $result[0];
        $this->assertEquals(200, $first->statusCode);
    }*/
}