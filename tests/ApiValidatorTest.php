<?php

use Apilyser\Analyser\Analyser;
use Apilyser\ApiValidator;
use Apilyser\Comparison\EndpointResult;
use Apilyser\Comparison\ValidationError;
use Apilyser\Definition\EndpointDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class ApiValidatorTest extends TestCase
{

    function testNoErrors()
    {
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            folderPath: "/test",
            output: $this->createMock(OutputInterface::class),
            analyser: $analyserMock
        );

        $analyserMock
            ->method("analyseRoutes")
            ->willReturn([
                new EndpointResult(
                    endpoint: new EndpointDefinition(
                        path: "/test",
                        method: "GET",
                        parameters: null,
                        response: null
                    ),
                    success: true,
                )
            ]);

        $analyserMock
            ->expects($this->once())
            ->method("analyseRoutes");

        $result = $validator->run();

        $this->assertEquals(expected: Command::SUCCESS, actual: $result);
    }

    function testOnlyErrors()
    {
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            folderPath: "/test",
            output: $this->createMock(OutputInterface::class),
            analyser: $analyserMock
        );

        $analyserMock
            ->method("analyseRoutes")
            ->willReturn([
                new EndpointResult(
                    endpoint: new EndpointDefinition(
                        path: "/test",
                        method: "GET",
                        parameters: null,
                        response: null
                    ),
                    success: false,
                    errors: [
                        new ValidationError(
                            errorType: "MissingEndpoint",
                            message: "Documentation for endpoint is missing",
                            errors: []
                        )
                    ]
                )
            ]);

        $analyserMock
            ->expects($this->once())
            ->method("analyseRoutes");

        $result = $validator->run();

        $this->assertEquals(expected: Command::FAILURE, actual: $result);
    }

    function testErrorsAndSuccess()
    {
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            folderPath: "/test",
            output: $this->createMock(OutputInterface::class),
            analyser: $analyserMock
        );

        $analyserMock
            ->method("analyseRoutes")
            ->willReturn([
                new EndpointResult(
                    endpoint: new EndpointDefinition(
                        path: "/test",
                        method: "GET",
                        parameters: null,
                        response: null
                    ),
                    success: false,
                    errors: [
                        new ValidationError(
                            errorType: "MissingEndpoint",
                            message: "Documentation for endpoint is missing",
                            errors: []
                        )
                    ]
                ),
                new EndpointResult(
                    endpoint: new EndpointDefinition(
                        path: "/test2",
                        method: "GET",
                        parameters: null,
                        response: null
                    ),
                    success: true,
                )
            ]);

        $analyserMock
            ->expects($this->once())
            ->method("analyseRoutes");

        $result = $validator->run();

        $this->assertEquals(expected: Command::FAILURE, actual: $result);
    }
}