<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\MethodPathDefinition;
use Apilyser\Extractor\MethodPathExtractor;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\MethodResolverStrategy;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Resolver\TypeStructureResolver;
use Apilyser\Traverser\ClassUsageTraverser;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;

class MethodAnalyser implements MethodResolverStrategy
{
    
    public function __construct(
        private MethodPathExtractor $methodPathExtractor,
        private ResponseResolver $responseResolver,
        private HttpDelegate $httpDelegate,
        private ClassUsageTraverserFactory $classUsageTraverserFactory,
        private TypeStructureResolver $typeStructureResolver
    ) {
        $this->typeStructureResolver->setMethodStrategy($this);
    }

    public function resolveMethod(ClassMethodContext $context): array
    {
        return $this->analyse($context);
    }

    public function analyse(ClassMethodContext $context): array
    {
        $paths = $this->methodPathExtractor->extract($context->method);

        $results = [];
        foreach ($paths as $path) {
            $result = $this->analysePath($path, $context);
            array_push(
                $results,
                ...$result
            );
        }

        return $results;
    }

    /**
     * @param MethodPathDefinition $path
     * @param ClassMethodContext $context
     * 
     * @return ResponseCall[]
     */
    private function analysePath(MethodPathDefinition $path, ClassMethodContext $context): array
    {
        $usedResponseClasses = $this->findUsedResponseClassesInPath($path, $context);
        $returns = $this->findReturnsInPath($path, $context, $usedResponseClasses);

        $statementNodes = array_map(
            fn($statement) => $statement->getNode(),
            $path->getStatements()
        );

        $results = [];
        $classResults = $this->responseResolver->resolveUsedClasses($context, $statementNodes, $usedResponseClasses);
        array_push($results, ...$classResults);

        $returnsResult = $this->responseResolver->resolveReturns($context, $statementNodes, $returns);
        array_push($results, ...$returnsResult);

        return $results;
    }

    /**
     * @return ClassUsage[]
     */
    private function findUsedResponseClassesInPath(MethodPathDefinition $path, ClassMethodContext $context): array
    {
        $usedResponseClasses = [];

        foreach ($this->httpDelegate->getParsers() as $httpParser) {
            $usedClass = $this->processClassInPath($path, $httpParser, $context->imports);
            array_push(
                $usedResponseClasses,
                ...$usedClass
            );
        }

        return $usedResponseClasses;
    }

    /**
     * @return Node[]
     */
    private function findReturnsInPath(MethodPathDefinition $path, ClassMethodContext $context): array
    {
        $returns = [];

        foreach ($path->getStatements() as $stmts) {
            $node = $stmts->getNode();

            if ($node instanceof Return_) {
                $returns[] = $node;
            }
        }

        return $returns;
    }

    /**
     * @return ClassUsage[]
     */
    private function processClassInPath(MethodPathDefinition $path, ApiParser $httpParser, array $imports): array
    {
        $usedClasses = $httpParser->getSupportedResponseClasses();

        /** @var ClassUsage[] */
        $usages = [];

        foreach ($path->getStatements() as $stmts) {
            $node = $stmts->getNode();

            foreach ($usedClasses as $usedClass) {
                $traverser = $this->classUsageTraverserFactory->create(
                    className: $usedClass,
                    imports: $imports
                );

                $this->processNode($node, $traverser);
                array_push($usages, ...$traverser->getUsages());
            }
        }

        return $usages;
    }

    private function processNode(Node $node, ClassUsageTraverser $traverser)
    {   
        $traverser->enterNode($node);

        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;

            if ($subNode instanceof Node) {
                $this->processNode($subNode, $traverser);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->processNode($item, $traverser);
                    }
                }
            }
        }
    }
}