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
use PhpParser\NodeDumper;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseAnalyser
{

    public function __construct(
        private OutputInterface $output,
        private NodeDumper $nodeDumper,
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

        $usedResponseClasses = [];
        foreach ($paths as $path) {
            foreach ($this->httpDelegate->getParsers() as $httpParser) {

                $usedClass = $this->processPath($path, $httpParser, $context->imports);
                array_push(
                    $usedResponseClasses,
                    ...$usedClass
                );
            }
        }

        //$this->output->writeln("HEJ: " . $this->nodeDumper->dump($context->method->stmts));
        
        // Find used classes in method that exist in api parser
        $results = $this->responseResolver->resolve($context, $usedResponseClasses);

        $result = array_map(
            function(ResponseCall $responseCall) {
                return $this->mapResponseCallToResponseDefinition($responseCall);
            },
            $results
        );

        $items = array_unique($result);

        foreach ($items as $item) {
            $this->output->writeln("Response: " . $item->toString());
        }

        return $items;
    }

    /**
     * @return ClassUsage[]
     */
    private function processPath(MethodPathDefinition $path, ApiParser $httpParser, array $imports): array
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