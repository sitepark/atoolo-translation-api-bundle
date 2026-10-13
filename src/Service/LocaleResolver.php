<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

/**
 * Maps a locale requested by a client (e.g. `en-GB` from i18next) to the
 * spelling used by the translation catalogues. Symfony compares locales as
 * exact strings, so `en-GB` would not find the messages of `en_GB`.
 */
class LocaleResolver
{
    private const string LOCALE_PATTERN = '/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})*$/';

    /**
     * @param list<string> $locales allowed locales, empty allows every
     *     well-formed locale in its ICU spelling (`en_GB`, `zh_Hans`)
     */
    public function __construct(
        private readonly array $locales,
    ) {}

    /**
     * @return string|null the configured spelling of the locale or `null`
     *     if the locale is malformed or not allowed
     */
    public function resolve(string $requested): ?string
    {
        if (preg_match(self::LOCALE_PATTERN, $requested) !== 1) {
            return null;
        }
        if ($this->locales === []) {
            return \Locale::canonicalize($requested);
        }
        $normalized = $this->normalize($requested);
        foreach ($this->locales as $locale) {
            if ($this->normalize($locale) === $normalized) {
                return $locale;
            }
        }
        return null;
    }

    private function normalize(string $locale): string
    {
        return strtolower(str_replace('_', '-', $locale));
    }
}
