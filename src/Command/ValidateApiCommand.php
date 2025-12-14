<?php declare(strict_types=1);

namespace Apilyser\Command;

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
    
        $rootPath = getcwd();

        try {
            $injection = new Injection(
                output: $output, 
                rootPath: $rootPath
            );
            
            $validator = $injection->createApiValidator();
            return $validator->run();
            
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}