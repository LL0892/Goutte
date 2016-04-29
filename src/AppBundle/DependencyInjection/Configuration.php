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
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('url')->end()
                            ->scalarNode('formNode')->end()
                            ->scalarNode('inputKey')->end()
                            ->variableNode('formInputs')->end()
                            ->scalarNode('mainNode')->end()
                            ->scalarNode('titleNode')->end()
                            ->booleanNode('titleStandardNode')
                                ->defaultTrue()
                            ->end()
                            ->scalarNode('priceNode')->end()
                            ->scalarNode('urlNode')->end()
                            ->scalarNode('imageNode')->end()
                            ->scalarNode('descNode')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}