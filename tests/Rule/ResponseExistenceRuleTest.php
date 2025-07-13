<?php

use ApiValidator\Comparison\ValidationError;
use ApiValidator\Definition\EndpointDefinition;
use ApiValidator\Definition\ResponseBodyDefinition;
use ApiValidator\Definition\ResponseDefinition;
use ApiValidator\Rule\ResponseExistenceRule;
use PHPUnit\Framework\TestCase;

class ResponseExistenceRuleTest extends TestCase
{

    function testResponseExistenceRuleWithNoResponse()
    {
        $rule = new ResponseExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
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
            $this->assertCount(expectedCount: 2, haystack: $result->errors);
        }
    }

    function testResponseExistenceRuleWithMissingDocResponse()
    {
        $rule = new ResponseExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: []
        );

        $code = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: null,
                    statusCode: 200
                )
            ]
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

    function testResponseExistenceRuleWithMissingCodeResponse()
    {
        $rule = new ResponseExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: null,
                    statusCode: 200
                )
            ]
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
            $this->assertCount(expectedCount: 2, haystack: $result->errors);
        }
    }

    function testResponseExistenceRuleWithDifferentStatusCodeResponse()
    {
        $rule = new ResponseExistenceRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: null,
                    statusCode: 200
                )
            ]
        );

        $code = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: null,
                    statusCode: 201
                )
            ]
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