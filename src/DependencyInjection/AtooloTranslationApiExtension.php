<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AtooloTranslationApiExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $value) {
            $container->setParameter('atoolo_translation_api.' . $key, $value);
        }
        if ($config['locales'] === []) {
            $container->setParameter(
                'atoolo_translation_api.locales',
                '%kernel.enabled_locales%',
            );
        }

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config'),
        );
        $loader->load('services.yaml');
    }
}
