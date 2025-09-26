<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\MethodPathDefinition;
use Apilyser\Definition\ResponseDefinition;
use Apilyser\Parser\Api\ApiParser;
use Apilyser\Parser\Api\HttpDelegate;
use Apilyser\Resolver\ApiFrameworkResolver;
use Apilyser\Resolver\ResponseCall;
use Apilyser\Resolver\ResponseResolver;
use Apilyser\Traverser\ClassUsageTraverser;
use Apilyser\Traverser\ClassUsageTraverserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseAnalyser
{
    public function __construct(
        private OutputInterface $output,
        private MethodPathAnalyser $methodPathAnalyser,
        private ApiFrameworkResolver $apiFrameworkResolver,
        private ResponseResolver $responseResolver,
        private HttpDelegate $httpDelegate,
        private ClassUsageTraverserFactory $classUsageTraverserFactory
    ) {}

    /**
     * @param ClassMethodContext $context
     * 
     * @return ResponseDefinition[]
     */
    public function analyse(ClassMethodContext $context): array
    {
        $paths = $this->methodPathAnalyser->analyse($context->method);

        $results = [];
        foreach ($paths as $path) {
            $result = $this->analysePath($path, $context);
            array_push(
                $results,
                ...$result
            );
        }

        $result = array_map(
            function(ResponseCall $responseCall) {
                return $this->mapResponseCallToResponseDefinition($responseCall);
            },
            $results
        );

        $res = array_unique($result);

        foreach ($res as $response) {
            $this->output->writeln("Response: " . $response->toString());
        }

        return $res;
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
            function($statement) {
                return $statement->getNode();
            },
            $path->getStatements()
        );

        $results = $this->responseResolver->resolveUsedClasses($context, $statementNodes, $usedResponseClasses);
        $returnsResult = $this->responseResolver->resolveReturns($context, $statementNodes, $returns);

        array_push(
            $results,
            ...$returnsResult
        );

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

                array_push(
                    $usages,
                    ...$traverser->getUsages()
                );
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

    /**
     * @param ResponseCall $call
     * 
     * @return ResponseDefinition
     */
    private function mapResponseCallToResponseDefinition(ResponseCall $call): ResponseDefinition
    {
        return new ResponseDefinition(
            type: $call->type,
            structure: $call->structure,
            statusCode: $call->statusCode
        );
    }

}