<?php

namespace Apilyser\Configuration;

use InvalidArgumentException;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class ConfigurationLoader
{

    private Processor $processor;
    private ConfigurationInterface $configuration;

    public function __construct()
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function loadFromFile(string $configPath): array
    {
        if (!file_exists($configPath)) {
            throw new InvalidArgumentException("Configuration file not found " . $configPath);
        }

        $yamlContent = Yaml::parseFile($configPath);

        // Process and validate configuration
        $processedConfig = $this->processor->processConfiguration($this->configuration, [$yamlContent]);
        return $processedConfig;
    }

}