<?php

namespace Apilyser\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public const CONFIG_PATH = "apilyser.yaml";
    public const CFG_CODE_PATH = "codePath";
    public const CFG_OPEN_API_PATH = "openApiPath";

    function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder("ApiValidator");

        $builder->getRootNode()
            ->children()
                ->scalarNode(self::CFG_CODE_PATH)
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode(self::CFG_OPEN_API_PATH)
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $builder;
    }
}