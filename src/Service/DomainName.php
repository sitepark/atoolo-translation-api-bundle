<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Domains are either plain (`frontend`) or in ICU message format
 * (`frontend+intl-icu`). Both spellings refer to the same domain.
 */
final class DomainName
{
    public static function base(string $domain): string
    {
        return str_ends_with($domain, MessageCatalogueInterface::INTL_DOMAIN_SUFFIX)
            ? substr($domain, 0, -strlen(MessageCatalogueInterface::INTL_DOMAIN_SUFFIX))
            : $domain;
    }

    public static function icu(string $domain): string
    {
        return self::base($domain) . MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;
    }
}
