<?php

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Definition\ParameterDefinition;
use Apilyser\Definition\RequestType;
use Apilyser\Rule\ParameterExistenceRule;
use PHPUnit\Framework\TestCase;

class ParameterExistenceRuleTest extends TestCase
{

    function testParameterExistenceRuleWithNoParameters()
    {
        $rule = new ParameterExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts",
            method: "GET",
            parameters: [],
            response: []
        );

        $code = new EndpointDefinition(
            path: "/api/v1/posts",
            method: "GET",
            parameters: [],
            response: []
        );

        $result = $rule->validate(
            openApiSpec: $doc,
            endpoint: $code
        );

        $this->assertInstanceOf(expected: ValidationSuccess::class, actual: $result);
    }


    function testParameterExistenceWithMissingDocParameter()
    {
        $rule = new ParameterExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
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

        $this->assertInstanceOf(expected: ValidationError::class, actual: $result);
        if ($result instanceof ValidationError) {
            $this->assertCount(expectedCount: 1, haystack: $result->errors);
        }
    }

    function testParameterExistenceWithMissingCodeParameter()
    {
        $rule = new ParameterExistenceRule();

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
            parameters: [],
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

    function testParameterExistenceWithWrongLocationParameter()
    {
        $rule = new ParameterExistenceRule();

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
                    location: RequestType::Path,
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
            $this->assertCount(expectedCount: 2, haystack: $result->errors);
        }
    }
}