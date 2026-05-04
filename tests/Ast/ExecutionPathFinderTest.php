<?php

namespace Apilyser\tests\Ast;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Ast\ExecutionPathFinder;
use Apilyser\Ast\Node\NameHelper;
use Override;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;

class ExecutionPathFinderTest extends TestCase
{
    private ExecutionPathFinder $executionPathFinder;

    #[Override]
    protected function setUp(): void
    {
        $this->executionPathFinder = new ExecutionPathFinder();
        parent::setUp();
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

    public function testFindAmountExecutionPaths()
    {
        $classMethod = $this->parseDataClassMethod('testExample');
        $paths = $this->executionPathFinder->extract($classMethod->method);

        assertCount(expectedCount: 48, haystack: $paths);
    }

}