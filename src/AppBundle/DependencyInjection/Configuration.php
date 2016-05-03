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
                ->booleanNode('debug')
                    ->defaultFalse()
                ->end()
                ->arrayNode('sites')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('parseUrl')->end()
                            ->scalarNode('baseUrl')->end()
                            ->scalarNode('formNode')->end()
                            ->scalarNode('inputKey')->end()
                            ->variableNode('formInputs')->end()
                            ->scalarNode('mainNode')->end()
                            ->scalarNode('titleNode')->end()
                            ->booleanNode('titleStandardNode')
                                ->defaultTrue()
                            ->end()
                            ->scalarNode('priceNode')->end()
                            ->arrayNode('urlNode')
                                ->children()
                                    ->scalarNode('value')->end()
                                    ->enumNode('type')
                                        ->values(array('absolute', 'relative'))
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('imageNode')
                                ->children()
                                    ->scalarNode('value')->end()
                                    ->enumNode('type')
                                        ->values(array('absolute', 'relative'))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}