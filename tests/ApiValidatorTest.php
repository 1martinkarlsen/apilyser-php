<?php

use Apilyser\Analyser\Analyser;
use Apilyser\ApiValidator;
use Apilyser\Comparison\EndpointResult;
use Apilyser\Comparison\ValidationError;
use Apilyser\Definition\EndpointDefinition;
use Apilyser\Parser\FileParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function PHPSTORM_META\map;

class ApiValidatorTest extends TestCase
{

    function testNoFiles()
    {
        $fileParserMock = $this->createMock(FileParser::class);
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            output: $this->createMock(OutputInterface::class),
            fileParser: $fileParserMock,
            analyser: $analyserMock
        );

        $fileParserMock
            ->method("getFiles")
            ->willReturn([]);

        $result = $validator->run();

        $this->assertEquals(expected: Command::SUCCESS, actual: $result);
    }

    function testNoErrors()
    {
        $fileParserMock = $this->createMock(FileParser::class);
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            output: $this->createMock(OutputInterface::class),
            fileParser: $fileParserMock,
            analyser: $analyserMock
        );

        $fileParserMock
            ->method("getFiles")
            ->willReturn(["filePath.php"]);

        $analyserMock
            ->method("analyse")
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

        $result = $validator->run();

        $this->assertEquals(expected: Command::SUCCESS, actual: $result);
    }

    function testOnlyErrors()
    {
        $fileParserMock = $this->createMock(FileParser::class);
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            output: $this->createMock(OutputInterface::class),
            fileParser: $fileParserMock,
            analyser: $analyserMock
        );

        $fileParserMock
            ->method("getFiles")
            ->willReturn(["filePath.php"]);

        $analyserMock
            ->method("analyse")
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

        $result = $validator->run();

        $this->assertEquals(expected: Command::FAILURE, actual: $result);
    }

    function testErrorsAndSuccess()
    {
        $fileParserMock = $this->createMock(FileParser::class);
        $analyserMock = $this->createMock(Analyser::class);

        $validator = new ApiValidator(
            output: $this->createMock(OutputInterface::class),
            fileParser: $fileParserMock,
            analyser: $analyserMock
        );

        $fileParserMock
            ->method("getFiles")
            ->willReturn(["filePath.php"]);

        $analyserMock
            ->method("analyse")
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

        $result = $validator->run();

        $this->assertEquals(expected: Command::FAILURE, actual: $result);
    }
}