<?php

namespace Apilyser;

use Apilyser\Analyser\Analyser;
use Apilyser\Parser\FileParser;
use Apilyser\Parser\RouteParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class ApiValidator
{

    public function __construct(
        private string $folderPath,
        private OutputInterface $output,
        private FileParser $fileParser,
        private Analyser $analyser,
        private RouteParser $routeParser
    ) {}

    function run(): int
    {
        $this->output->writeln("<info>Starting validation</info>");

        $errors = [];
        $validationResults =$this->analyser->analyseRoutes($this->folderPath);
        foreach ($validationResults as $result) {
            if (!$result->success) {
                array_push($errors, $result);

                $this->output->writeln("" . $result->endpoint->method . " " . $result->endpoint->path . "");
                foreach ($result->errors as $error) {
                    $this->output->writeln("[" . $error->errorType . "] " . $error->getMessage());
                }
            }
        }

        if (!empty($errors)) {
            $this->output->writeln("<error>Apilyser validate failed</error>");
            return Command::FAILURE;
        }

        /*$files = $this->fileParser->getFiles();

        $errors = [];
        $validationResults = $this->analyser->analyse($files);
        foreach ($validationResults as $result) {
            if (!$result->success) {
                array_push($errors, $result);

                $this->output->writeln("" . $result->endpoint->method . " " . $result->endpoint->path . "");
                foreach ($result->errors as $error) {
                    $this->output->writeln("[" . $error->errorType . "] " . $error->getMessage());
                }
            }
        }

        if (!empty($errors)) {
            $this->output->writeln("<error>Apilyser validate failed</error>");
            return Command::FAILURE;
        }*/

        return Command::SUCCESS;
    }

}
