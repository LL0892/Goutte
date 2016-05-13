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
                    ->info('This define if we use a debug method to get the result of what is parsed.')
                ->end()
                ->arrayNode('sites')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')
                                ->info('This is the name of the site we want to parse.')
                            ->end()
                            ->enumNode('searchType')
                                ->values(array('formQuery', 'urlQuery'))
                                ->info('Define the search type: "formQuery OR urlQuery"')
                            ->end()
                            ->scalarNode('parseUrl')
                                ->info('This is the url used by the parser to get the data. If using "urlQuery", but the base url with the search key parameter.')
                            ->end()
                            ->scalarNode('baseUrl')
                                ->info('This is the base url used by the images and links to the parsed objects.')
                            ->end()
                            ->scalarNode('formNode')
                                ->info('This is the form Id.')
                            ->end()
                            ->scalarNode('inputKey')
                                ->info('This is form input name.')
                            ->end()
                            ->variableNode('formInputs')
                                ->info('This is all the other inputs we could fill if need to.')
                            ->end()
                            ->scalarNode('mainNode')
                                ->info('This is the CSS selector at the root of the parsed object.')
                            ->end()
                            ->scalarNode('titleNode')
                                ->info('This is the CSS selector for the object title.')
                            ->end()
                            ->scalarNode('titleNodeParsing')
                                ->defaultValue('innerHTML')
                                ->info('If not innerHTML, this is the attribute name where the data is fetched for the title.')
                            ->end()
                            ->scalarNode('priceNode')
                                ->info('This is the CSS selector for the object price.')
                            ->end()
                            ->arrayNode('urlNode')
                                ->children()
                                    ->scalarNode('value')
                                        ->info('This is the CSS selector for object urls.')
                                    ->end()
                                    ->enumNode('type')
                                        ->values(array('absolute', 'relative'))
                                        ->info('absolute: use this url only to compose the final url, relative: use baseUrl + this one to compose the final url.')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('imageNode')
                                ->children()
                                    ->scalarNode('value')
                                        ->info('This is the CSS selector for object image sources.')
                                    ->end()
                                    ->enumNode('type')
                                        ->values(array('absolute', 'relative'))
                                        ->info('absolute: use this url only to compose the final url, relative: use baseUrl + this one to compose the final url.')
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