<?php

use ApiValidator\Comparison\ValidationError;
use ApiValidator\Comparison\ValidationSuccess;
use ApiValidator\Definition\EndpointDefinition;
use ApiValidator\Definition\ResponseBodyDefinition;
use ApiValidator\Definition\ResponseDefinition;
use ApiValidator\Rule\ResponseSchemePropertiesRule;
use PHPUnit\Framework\TestCase;

class ResponseSchemePropertiesRuleTest extends TestCase
{

    function testResponseSchemePropertiesRuleSuccess()
    {
        $rule = new ResponseSchemePropertiesRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: [
                        new ResponseBodyDefinition(
                            name: 'username',
                            type: 'string',
                            nullable: false
                        )
                    ],
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
                    structure: [
                        new ResponseBodyDefinition(
                            name: 'username',
                            type: 'string',
                            nullable: false
                        )
                    ],
                    statusCode: 200
                )
            ]
        );

        $result = $rule->validate(
            openApiSpec: $doc,
            endpoint: $code
        );

        $this->assertInstanceOf(expected: ValidationSuccess::class, actual: $result);
    }

    function testResponseSchemePropertiesRuleWithMissingCodeResponseBody()
    {
        $rule = new ResponseSchemePropertiesRule();

        $doc = new EndpointDefinition(
            path: "/api/v1/posts/{id}",
            method: "GET",
            parameters: [],
            response: [
                new ResponseDefinition(
                    type: "application/json",
                    structure: [
                        new ResponseBodyDefinition(
                            name: 'username',
                            type: 'string',
                            nullable: false
                        )
                    ],
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
                    structure: [],
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
            $this->assertCount(expectedCount: 1, haystack: $result->errors);
        }
    }
}