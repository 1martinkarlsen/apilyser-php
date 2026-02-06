<?php declare(strict_types=1);

namespace Apilyser;

use Apilyser\Analyser\Analyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class ApiValidator
{

    public function __construct(
        private string $folderPath,
        private OutputInterface $output,
        private Analyser $analyser
    ) {}

    public function run(): int
    {
        $this->output->writeln("<info>Starting validation</info>");

        $errors = [];
        $validationResults = $this->analyser->analyse($this->folderPath);
        foreach ($validationResults as $result) {
            if (!$result->success) {
                array_push($errors, $result);

                $this->output->writeln("<info>" . $result->endpoint->method . " " . $result->endpoint->path . "</info>");
                foreach ($result->errors as $error) {
                    $this->output->writeln("[" . $error->errorType . "] " . $error->getMessage());
                    foreach ($error->errors as $errorMessage) {
                        $this->output->writeln(" - " . $errorMessage);
                    }
                }
            }
        }

        if (!empty($errors)) {
            $this->output->writeln("<error>Apilyser validate failed</error>");
            return Command::FAILURE;
        }

        $this->output->writeln("<info>Apilyser validate succeeded</info>");
        return Command::SUCCESS;
    }

}
