<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\CatalogueExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(CatalogueExporter::class)]
class CatalogueExporterTest extends TestCase
{
    private CatalogueExporter $exporter;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->setFallbackLocales(['en']);
        $translator->addResource('array', [
            'greeting' => 'Hello',
            'cart' => ['items' => '{count, plural, one {# item} other {# items}}'],
            'only_en' => 'English only',
        ], 'en', 'frontend+intl-icu');
        $translator->addResource('array', [
            'legacy' => 'Legacy %name%',
        ], 'en', 'frontend');
        $translator->addResource('array', [
            'greeting' => 'Hallo',
            'cart' => ['items' => '{count, plural, one {# Artikel} other {# Artikel}}'],
        ], 'de', 'frontend+intl-icu');
        $translator->addResource('array', [
            'secret' => 'Secret',
        ], 'de', 'internal');

        $this->exporter = new CatalogueExporter($translator);
    }

    public function testExportMergesIcuAndPlainMessages(): void
    {
        $this->assertSame(
            [
                'greeting' => 'Hello',
                'cart.items' => '{count, plural, one {# item} other {# items}}',
                'only_en' => 'English only',
                'legacy' => 'Legacy %name%',
            ],
            $this->exporter->export('en', 'frontend'),
        );
    }

    public function testExportPrefersLocaleOverFallback(): void
    {
        $messages = $this->exporter->export('de', 'frontend');

        $this->assertSame('Hallo', $messages['greeting']);
        $this->assertSame(
            '{count, plural, one {# Artikel} other {# Artikel}}',
            $messages['cart.items'],
        );
        $this->assertSame('English only', $messages['only_en']);
    }

    public function testExportOnlyContainsRequestedDomain(): void
    {
        $this->assertArrayNotHasKey(
            'secret',
            $this->exporter->export('de', 'frontend'),
        );
    }

    public function testExportOfUnknownDomainIsEmpty(): void
    {
        $this->assertSame([], $this->exporter->export('de', 'unknown'));
    }
}
