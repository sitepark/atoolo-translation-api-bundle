<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Exports all messages of a translation domain for a locale, including the
 * messages of the fallback locales.
 */
class CatalogueExporter
{
    /**
     * Messages of the more specific locale win over those of its fallback
     * locales. Both the ICU (`domain+intl-icu`) and the plain domain are
     * included, as `MessageCatalogue::all()` merges them.
     *
     * @return array<string, string> flat map of translation key to message
     */
    public function export(TranslatorBagInterface $translator, string $locale, string $domain): array
    {
        $messages = [];
        $catalogue = $translator->getCatalogue($locale);
        while ($catalogue !== null) {
            $messages += $catalogue->all($domain);
            $catalogue = $catalogue->getFallbackCatalogue();
        }
        return $messages;
    }
}
