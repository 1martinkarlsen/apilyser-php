<?php declare(strict_types=1);

namespace Apilyser\Analyser;

use Apilyser\Definition\ParameterDefinition;
use Apilyser\Ast\MethodParameterFinder;
use Apilyser\Ast\RequestCallFinder;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Framework\FrameworkRegistry;
use Exception;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;

class RequestAnalyser
{

    public function __construct(
        private FrameworkRegistry $frameworkRegistry,
        private MethodParameterFinder $methodParameterFinder,
        private ParameterDefinitionFactory $parameterDefinitionFactory
    ) {}

    public function analyse(ClassMethodContext $context): array
    {
        return $this->analyzeMethod($context->method, $context->imports) ?: [];
    }

    /**
     * Parses a function to find requests
     *
     * @param ClassMethod $method
     * @param string[] $imports
     * @return ParameterDefinition[]
     */
    private function analyzeMethod(ClassMethod $method, array $imports): array {
        $parameterDefinitions = [];

        $methodParams = $this->methodParameterFinder->extract($method, $imports);

        foreach ($methodParams as $param) {
            if ($param->isBuiltinType) {
                $parameterDefinitions[] = $this->parameterDefinitionFactory->createPathDefinition($param);
            } else {
                if ($param->fullNamespace != null) {
                    $frameworkAdapter = $this->frameworkRegistry->getRequestParser($param->fullNamespace);

                    if ($frameworkAdapter != null) {
                        $requestCallFinder = new RequestCallFinder($frameworkAdapter);
                        $requestCalls = $requestCallFinder->findCalls($method->stmts, $param->name);

                        foreach ($requestCalls as $call) {
                            $paramDef = $this->parameterDefinitionFactory->createFromRequestCall($call, $param);
                            $parameterDefinitions[] = $paramDef;
                        }
                    }
                }
            }
        }

        return $parameterDefinitions;
    }
}
