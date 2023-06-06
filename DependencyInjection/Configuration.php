<?php

namespace evaisse\SimpleHttpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder() : TreeBuilder
    {
        $treeBuilder = new TreeBuilder('simple_http');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('blackfire')
                    ->children()
                        ->scalarNode('client_id')
                            ->defaultValue(null)
                        ->end()
                        ->scalarNode('client_token')
                            ->defaultValue(null)
                        ->end()
                        ->scalarNode('samples')
                            ->defaultValue(10)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
