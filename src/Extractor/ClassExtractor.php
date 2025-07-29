<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Used for extracting usage of classes provided by HttpDelegate
 */
class ClassExtractor
{
    public function __construct(
        private ClassUsageTraverserFactory $classUsageTraverserFactory,
        private HttpDelegate $httpDelegate
    ) {}

    /**
     * @param ClassMethod $method
     * @param string[] $imports
     * 
     * @return ClassUsage[]
     */
    function extract(ClassMethod $method, array $imports): array
    {
        $result = [];

        foreach ($this->httpDelegate->getParsers() as $http) {
            $usages = $this->traverseResponse(
                apiParser: $http,
                stmts: $method->stmts,
                imports: $imports
            );

            foreach ($usages as $usage) {
                $result[] = $usage;
            }
        }

        return $result;
    }

    /**
     * @param ApiParser $apiParser
     * @param Node[] $stmts
     * @param string[] $imports
     * 
     * @return ClassUsage[]
     */
    private function traverseResponse(ApiParser $apiParser, array $stmts, array $imports)
    {
        // Establish parent relationships
        $traverser = new NodeTraverser();
        $parentConnector = new ParentConnectingVisitor();
        $traverser->addVisitor($parentConnector);
        $ast = $traverser->traverse($stmts);

        // Find class usage
        $tt = new NodeTraverser();
        $finder = $this->classUsageTraverserFactory->create(
            apiParser: $apiParser,
            imports: $imports
        );
        $tt->addVisitor($finder);
        $tt->traverse($ast);

        return $finder->getUsages();
    }
}