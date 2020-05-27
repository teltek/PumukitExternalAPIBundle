<?php

namespace Pumukit\ExternalAPIBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pumukit_external_api');

        $rootNode
            ->children()
            ->scalarNode('allowed_removed_tag')
            ->defaultValue('CUSTOM_TAG')
            ->isRequired()
            ->info('Define which tag will be used to mark objects which can be subjected to tag removal')
            ->end()
        ;

        return $treeBuilder;
    }
}
