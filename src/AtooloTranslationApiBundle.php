<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi;

use Atoolo\TranslationApi\DependencyInjection\AtooloTranslationApiExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
class AtooloTranslationApiBundle extends Bundle
{
    public function getContainerExtension(): AtooloTranslationApiExtension
    {
        return new AtooloTranslationApiExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
