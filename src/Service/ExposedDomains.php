<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

/**
 * Decides whether a domain may be fetched without full access. Domains are
 * compared without `+intl-icu` suffix, `*` exposes every domain.
 */
class ExposedDomains
{
    public const ALL = '*';

    /**
     * @var list<string>
     */
    private readonly array $domains;

    /**
     * @param list<string> $domains exposed domains with or without
     *     `+intl-icu` suffix, `*` exposes every domain
     */
    public function __construct(array $domains)
    {
        $this->domains = array_map(DomainName::base(...), $domains);
    }

    public function isExposed(string $domain): bool
    {
        return in_array(self::ALL, $this->domains, true)
            || in_array(DomainName::base($domain), $this->domains, true);
    }
}
