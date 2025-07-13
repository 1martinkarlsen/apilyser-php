<?php

namespace Apilyser\Analyser;

use Apilyser\Definition\ParameterDefinition;
use Apilyser\Extractor\MethodParameterExtractor;
use Apilyser\Extractor\RequestUsageExtractor;
use Apilyser\Definition\ParameterDefinitionFactory;
use Apilyser\Parser\Api\HttpDelegate;
use Exception;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;

class RequestAnalyser
{

    public function __construct(
        private HttpDelegate $httpDelegate,
        private MethodParameterExtractor $methodParameterExtractor,
        private ParameterDefinitionFactory $parameterDefinitionFactory
    ) {}

    public function analyse(ClassMethodContext $context): array
    {
        if (!$context instanceof ClassMethodContext) {
            throw new Exception("Wrong context type");
        }

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

        $methodParams = $this->methodParameterExtractor->extract($method, $imports);

        foreach ($methodParams as $param) {
            if ($param->isBuiltinType) {
                $parameterDefinitions[] = $this->parameterDefinitionFactory->createPathDefinition($param);
            } else {
                if ($param->fullNamespace != null) {
                    $apiParser = $this->httpDelegate->getRequestParser($param->fullNamespace);

                    if ($apiParser != null) {
                        $requestUsageExtractor = new RequestUsageExtractor($apiParser);
                        $requestCalls = $requestUsageExtractor->findCalls($method->stmts, $param->name);

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