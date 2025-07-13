<?php

namespace Apilyser\Analyser;

use Apilyser\Comparison\ApiComparison;
use Symfony\Component\Console\Output\OutputInterface;

final class Analyser
{

    public function __construct(
        private OutputInterface $output,
        private OpenApiAnalyser $openApiAnalyser,
        private FileAnalyser $fileAnalyser,
        private EndpointAnalyser $endpointAnalyser,
        private ApiComparison $comparison
    ) {}

    public function analyse(array $files)
    {
        $spec = $this->openApiAnalyser->analyse();
        if ($spec == null) {
            $this->output->writeln("<error>Could not find Open API documentation</error>");
            return;
        }

        // Analyse all files
        $endpoints = [];
        foreach($files as $filePath) {
            $endpoint = $this->fileAnalyser->analyse($filePath);

            array_push(
                $endpoints,
                ...$endpoint
            );
        }

        $this->comparison->compare(
            code: $endpoints,
            spec: $spec
        );
    }
}