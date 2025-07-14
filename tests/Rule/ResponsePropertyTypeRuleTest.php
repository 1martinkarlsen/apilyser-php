<?php

use Apilyser\Comparison\ValidationError;
use Apilyser\Comparison\ValidationSuccess;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Definition\ResponseBodyDefinition;
use Apilyser\Definition\ResponseDefinition;
use Apilyser\Rule\ResponsePropertyTypeRule;
use PHPUnit\Framework\TestCase;

class ResponsePropertyTypeRuleTest extends TestCase
{

    function testResponsePropertyRuleSameBodyResponse()
    {
        $rule = new ResponsePropertyTypeRule();

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

    function testResponsePropertyTypeRuleWithDifferentBodyResponse()
    {
        $rule = new ResponsePropertyTypeRule();

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
                            type: 'int',
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

        $this->assertInstanceOf(expected: ValidationError::class, actual: $result);
        if ($result instanceof ValidationError) {
            $this->assertEquals(expected: "ResponsePropertyTypeRule", actual: $result->errorType);
            $this->assertCount(expectedCount: 1, haystack: $result->errors);
        }
    }
}