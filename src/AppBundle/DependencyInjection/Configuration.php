<?php

namespace Acme\DatabaseConfiguration;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class DatabaseConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('parsing');

        $rootNode
            ->children()
                ->arrayNode('sites')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('url')->end()
                            ->scalarNode('mainNode')->end()
                            ->scalarNode('titleNode')->end()
                            ->booleanNode('titleStandardNode')->end()
                            ->scalarNode('priceNode')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}