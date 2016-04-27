<?php

namespace AppBundle\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('app');

        $rootNode
            ->children()
                ->arrayNode('sites')

                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('url')->end()
                            ->scalarNode('formNode')->end()
                            ->scalarNode('mainNode')->end()
                            ->scalarNode('titleNode')->end()
                            ->booleanNode('titleStandardNode')
                                ->defaultTrue()
                            ->end()
                            ->scalarNode('priceNode')->end()
                        ->end()

                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}