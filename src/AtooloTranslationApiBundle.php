<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi;

use Atoolo\TranslationApi\DependencyInjection\AtooloTranslationApiExtension;
use Atoolo\TranslationApi\DependencyInjection\Compiler\ValidateSourcesPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
class AtooloTranslationApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // late, so that it also sees the sources other passes register
        $container->addCompilerPass(new ValidateSourcesPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getContainerExtension(): AtooloTranslationApiExtension
    {
        return new AtooloTranslationApiExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
