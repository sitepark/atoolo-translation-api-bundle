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
                    ->info('Translation domains that may be fetched without full access, with or without "+intl-icu" suffix. "*" exposes every domain. Everything else responds with 404.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('locales')
                    ->info('Allowed locales in the spelling of the translation files. Defaults to framework.enabled_locales, if both are empty every well-formed locale is allowed in its ICU spelling (en_GB).')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('full_access_role')
                    ->info('Role (or voter attribute) of users that may fetch every domain and well-formed locale, regardless of "domains" and "locales". Requires the security bundle, null disables full access.')
                    ->cannotBeEmpty()
                    ->defaultNull()
                ->end()
                ->integerNode('cache_max_age')
                    ->info('Value of the max-age directive of the Cache-Control header in seconds.')
                    ->min(0)
                    ->defaultValue(300)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
