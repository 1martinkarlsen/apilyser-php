<?php

namespace Apilyser\Command;

use Apilyser\ApiValidator;
use Apilyser\Configuration\Configuration;
use Apilyser\Configuration\ConfigurationLoader;
use Apilyser\Di\Injection;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'validate',
    description: 'Validate the API',
    hidden: false
)]
class ValidateApiCommand extends Command
{

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configLoader = new ConfigurationLoader();
        $cfg = $configLoader->loadFromFile(getcwd() . "/" . Configuration::CONFIG_PATH);

        $injection = new Injection(
            output: $output, 
            rootPath: getcwd(),
            configuration: $cfg
        );

        $validator = $injection->get(ApiValidator::class);
        
        try {
            return $validator->run();
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}