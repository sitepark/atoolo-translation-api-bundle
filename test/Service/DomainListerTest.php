<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\DomainLister;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(DomainLister::class)]
class DomainListerTest extends TestCase
{
    private function createLister(): DomainLister
    {
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['a' => 'A'], 'de', 'frontend');
        $translator->addResource('array', ['b' => 'B'], 'de', 'frontend+intl-icu');
        $translator->addResource('array', ['a' => 'A'], 'de', 'validators');
        $translator->addResource('array', ['a' => 'A'], 'en', 'internal+intl-icu');
        $translator->addResource('array', ['a' => 'A'], 'en', 'validators+intl-icu');
        $translator->addResource('array', ['a' => 'A'], 'fr', 'legacy');

        return new DomainLister($translator);
    }

    public function testListsDomainsInFullIncludingFallbackLocales(): void
    {
        // "validators" is plain in "de", but ICU in the fallback locale "en"
        $this->assertSame(
            ['frontend+intl-icu', 'internal+intl-icu', 'validators+intl-icu'],
            $this->createLister()->list('de'),
        );
    }

    public function testLocaleWithoutMessages(): void
    {
        $this->assertSame(
            ['internal+intl-icu', 'validators+intl-icu'],
            $this->createLister()->list('it'),
        );
    }
}
