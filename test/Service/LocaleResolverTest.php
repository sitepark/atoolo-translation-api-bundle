<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\LocaleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocaleResolver::class)]
class LocaleResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string, ?string}>
     */
    public static function locales(): iterable
    {
        yield 'ICU spelling without allowlist' => [[], 'pt-BR', 'pt_BR'];
        yield 'ICU script spelling without allowlist' => [[], 'zh-hans', 'zh_Hans'];
        yield 'ICU region code without allowlist' => [[], 'es-419', 'es_419'];
        yield 'lowercase language without allowlist' => [[], 'DE', 'de'];
        yield 'malformed locale without allowlist' => [[], '../de', null];
        yield 'too short language' => [[], 'd', null];
        yield 'exact match' => [['de', 'en'], 'de', 'de'];
        yield 'configured underscore spelling' => [['en_GB'], 'en-GB', 'en_GB'];
        yield 'configured lowercase spelling' => [['en-gb'], 'en-GB', 'en-gb'];
        yield 'case insensitive language' => [['de'], 'DE', 'de'];
        yield 'not allowed' => [['de', 'en'], 'fr', null];
        yield 'region not allowed' => [['en'], 'en-GB', null];
        yield 'malformed with allowlist' => [['de'], 'de/../en', null];
    }

    /**
     * @param list<string> $allowed
     */
    #[DataProvider('locales')]
    public function testResolve(array $allowed, string $requested, ?string $expected): void
    {
        $resolver = new LocaleResolver($allowed);
        $this->assertSame($expected, $resolver->resolve($requested));
    }
}
