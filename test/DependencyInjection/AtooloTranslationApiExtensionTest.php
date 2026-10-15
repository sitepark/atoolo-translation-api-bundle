<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\DependencyInjection;

use Atoolo\TranslationApi\DependencyInjection\AtooloTranslationApiExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AtooloTranslationApiExtension::class)]
class AtooloTranslationApiExtensionTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.enabled_locales', ['de', 'en_GB']);
        (new AtooloTranslationApiExtension())->load([$config], $container);
        return $container;
    }

    public function testLocalesDefaultToEnabledLocales(): void
    {
        $container = $this->load([]);

        $this->assertSame(
            ['de', 'en_GB'],
            $container->getParameterBag()->resolveValue(
                $container->getParameter('atoolo_translation_api.locales'),
            ),
        );
    }

    public function testConfiguredLocalesWinOverEnabledLocales(): void
    {
        $container = $this->load(['locales' => ['fr']]);

        $this->assertSame(
            ['fr'],
            $container->getParameter('atoolo_translation_api.locales'),
        );
    }

    public function testSetsParameters(): void
    {
        $container = $this->load([
            'domains' => ['frontend'],
            'full_access_role' => 'ROLE_TRANSLATOR',
            'cache_max_age' => 60,
        ]);

        $this->assertSame(['frontend'], $container->getParameter('atoolo_translation_api.domains'));
        $this->assertSame('ROLE_TRANSLATOR', $container->getParameter('atoolo_translation_api.full_access_role'));
        $this->assertSame(60, $container->getParameter('atoolo_translation_api.cache_max_age'));
    }
}
