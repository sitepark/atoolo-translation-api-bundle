<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Lists the domains of a locale, including the domains of its fallback
 * locales, as their messages are delivered as well.
 */
class DomainLister
{
    public function __construct(
        private readonly TranslatorBagInterface $translator,
    ) {}

    /**
     * Domains are named in full, i.e. with the `+intl-icu` suffix if the
     * domain contains ICU messages in the locale or one of its fallback
     * locales.
     *
     * @return list<string> sorted domains
     */
    public function list(string $locale): array
    {
        $domains = [];
        $catalogue = $this->translator->getCatalogue($locale);
        while ($catalogue !== null) {
            // getDomains() strips the ICU suffix
            foreach ($catalogue->getDomains() as $domain) {
                $icu = DomainName::icu($domain);
                if ($catalogue->all($icu) !== []) {
                    $domains[$domain] = $icu;
                } else {
                    $domains[$domain] ??= $domain;
                }
            }
            $catalogue = $catalogue->getFallbackCatalogue();
        }
        $domains = array_values($domains);
        sort($domains);
        return $domains;
    }
}
