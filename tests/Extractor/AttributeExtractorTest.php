<?php

use ApiValidator\Extractor\AttributeExtractor;
use ApiValidator\Parser\NodeParser;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

class AttributeExtractorTest extends TestCase
{

    private AttributeExtractor $extractor;
    private NodeParser $nodeParser;
    private NodeFinder $nodeFinder;

    public function setUp(): void
    {
        $this->extractor = new AttributeExtractor();
        $this->nodeParser = new NodeParser();
        $this->nodeFinder = new NodeFinder();
    }

    function testNoAttributes()
    {
        $data = $this->createAstAttributeGroups(__DIR__ . '/Data/EmptyClass.php');
        $result = $this->extractor->extract($data, "Route");

        $this->assertNull($result);
    }

    function testWithAttribute()
    {
        $data = $this->createAstAttributeGroups(__DIR__ . '/Data/RouteClass.php');
        $result = $this->extractor->extract($data, "Route");

        $this->assertNotNull($result);
        $this->assertEquals(expected: "Route", actual: $result->name->name);
    }

    function testWithMultipleAttributes()
    {
        $data = $this->createAstAttributeGroups(__DIR__ . '/Data/MultiRouteClass.php');
        $result = $this->extractor->extract($data, "Route");

        $this->assertNotNull($result);
        $this->assertEquals(expected: "Route", actual: $result->name->name);
    }

    private function createAstAttributeGroups($filePath): array
    {
        $fileContent = file_get_contents($filePath);
        $data = $this->nodeParser->parse($fileContent);
        $class = $this->nodeFinder->findFirstInstanceOf($data, Class_::class);
        return $class->attrGroups;
    }
}