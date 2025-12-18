<?php declare(strict_types=1);

namespace Apilyser\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public const CONFIG_PATH = "apilyser.yaml";
    public const CFG_CODE_PATH = "codePath";
    public const CFG_OPEN_API_PATH = "openApiPath";
    public const CFG_CUSTOM_ROUTE_PARSER = "customRouteParser";

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
                ->arrayNode(self::CFG_CUSTOM_ROUTE_PARSER)
                ->end()
            ->end();

        return $builder;
    }
}