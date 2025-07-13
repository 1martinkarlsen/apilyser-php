<?php

namespace Apilyser;

use Apilyser\Definition\ParameterDefinition;
use Apilyser\Definition\RequestType;
use Apilyser\Extractor\MethodParam;
use Apilyser\Extractor\RequestCall;

class ParameterDefinitionFactory
{

    public function createPathDefinition(MethodParam $methodParam): ParameterDefinition
    {
        return new ParameterDefinition(
            name: $methodParam->name,
            type: $methodParam->type,
            location: RequestType::Path,
            required: true,
            default: null
        );
    }

    public function createFromRequestCall(RequestCall $call, MethodParam $methodParam): ParameterDefinition
    {
        return new ParameterDefinition(
            name: $call->parameterName,
            type: $call->deducedType,
            location: $call->source,
            required: true,
            default: null
        );
    }
}