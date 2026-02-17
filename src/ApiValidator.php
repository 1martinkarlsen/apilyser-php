<?php declare(strict_types=1);

namespace Apilyser;

use Apilyser\Analyser\Analyser;
use Apilyser\Util\Logger;
use Symfony\Component\Console\Command\Command;

class ApiValidator
{

    public function __construct(
        private string $folderPath,
        private Logger $logger,
        private Analyser $analyser
    ) {}

    public function run(): int
    {
        $this->logger->log("<info>Starting validation</info>");

        $errors = [];
        $validationResults = $this->analyser->analyse($this->folderPath);
        foreach ($validationResults as $result) {
            if (!$result->success) {
                array_push($errors, $result);

                $this->logger->log("<info>" . $result->endpoint->method . " " . $result->endpoint->path . "</info>");
                foreach ($result->errors as $error) {
                    $this->logger->log("[" . $error->errorType . "] " . $error->getMessage());
                    foreach ($error->errors as $errorMessage) {
                        $this->logger->log(" - " . $errorMessage);
                    }
                }
            }
        }

        if (!empty($errors)) {
            $this->logger->log("<error>Apilyser validate failed</error>");
            return Command::FAILURE;
        }

        $this->logger->log("<info>Apilyser validate succeeded</info>");
        return Command::SUCCESS;
    }

}
