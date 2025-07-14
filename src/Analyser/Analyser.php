<?php

namespace Apilyser\Analyser;

use Apilyser\Comparison\ApiComparison;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

class Analyser
{

    public function __construct(
        private OutputInterface $output,
        private OpenApiAnalyser $openApiAnalyser,
        private FileAnalyser $fileAnalyser,
        private EndpointAnalyser $endpointAnalyser,
        private ApiComparison $comparison
    ) {}

    /**
     * @param string[] $files
     *
     * @return EndpointResult[]
     */
    public function analyse(array $files): array
    {
        $spec = $this->openApiAnalyser->analyse();
        if ($spec == null) {
            throw new Exception("Could not find Open API documentation");
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

        return $this->comparison->compare(
            code: $endpoints,
            spec: $spec
        );
    }
}