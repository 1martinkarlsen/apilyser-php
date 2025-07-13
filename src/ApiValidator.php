<?php

namespace Apilyser;

use Apilyser\Analyser\Analyser;
use Apilyser\Di\Injection;
use Apilyser\Parser\FileParser;
use Symfony\Component\Console\Output\OutputInterface;

class ApiValidator
{

    private OutputInterface $output;
    private Analyser $analyser;
    private FileParser $fileParser;

    public function __construct(
        Injection $injection
    ) {
        $this->output = $injection->get(OutputInterface::class);
        $this->fileParser = $injection->get(FileParser::class);
        $this->analyser = $injection->get(Analyser::class);
    }

    function run(): void
    {
        $this->output->writeln("<info>Starting validation</info>");
        $files = $this->fileParser->getFiles();

        $this->analyser->analyse($files);
    }

}
