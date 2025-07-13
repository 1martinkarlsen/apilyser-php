<?php

use ApiValidator\Comparison\ValidationError;
use ApiValidator\Comparison\ValidationSuccess;
use ApiValidator\Definition\EndpointDefinition;
use ApiValidator\Definition\ParameterDefinition;
use ApiValidator\Definition\RequestType;
use ApiValidator\Rule\ParameterTypeRule;
use PHPUnit\Framework\TestCase;

class ParameterTypeRuleTest extends TestCase
{

    function testParameterTypeRuleWithValidParameter()
    {
        $rule = new ParameterTypeRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [
                new ParameterDefinition(
                    name: 'id',
                    type: "int",
                    location: RequestType::Query,
                    required: true,
                    default: null
                )
            ],
            response: []
        );

        $code = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [
                new ParameterDefinition(
                    name: 'id',
                    type: "int",
                    location: RequestType::Query,
                    required: true,
                    default: null
                )
            ],
            response: []
        );

        $result = $rule->validate(
            openApiSpec: $doc,
            endpoint: $code
        );

        $this->assertInstanceOf(expected: ValidationSuccess::class, actual: $result);
    }

    function testParameterTypeRuleWithWrongTypeParameter()
    {
        $rule = new ParameterTypeRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [
                new ParameterDefinition(
                    name: 'id',
                    type: "int",
                    location: RequestType::Query,
                    required: true,
                    default: null
                )
            ],
            response: []
        );

        $code = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [
                new ParameterDefinition(
                    name: 'id',
                    type: "string",
                    location: RequestType::Query,
                    required: true,
                    default: null
                )
            ],
            response: []
        );

        $result = $rule->validate(
            openApiSpec: $doc,
            endpoint: $code
        );

        $this->assertInstanceOf(expected: ValidationError::class, actual: $result);
        if ($result instanceof ValidationError) {
            $this->assertCount(expectedCount: 1, haystack: $result->errors);
        }
    }

    
}