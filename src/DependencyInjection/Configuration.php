<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('atoolo_translation_api');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('domains')
                    ->info('Translation domains that may be fetched. Everything else responds with 404.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('locales')
                    ->info('Allowed locales in the spelling of the translation files. Defaults to framework.enabled_locales, if both are empty every well-formed locale is allowed in its ICU spelling (en_GB).')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->integerNode('cache_max_age')
                    ->info('Value of the max-age directive of the Cache-Control header in seconds.')
                    ->min(0)
                    ->defaultValue(300)
                ->end()
                ->arrayNode('cors_allow_origins')
                    ->info('Origins that may fetch the translations cross-origin, "*" allows every origin.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
