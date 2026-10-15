<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Controller;

use Atoolo\TranslationApi\Service\DomainLister;
use Atoolo\TranslationApi\Service\ExposedDomains;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\JsonResponseFactory;
use Atoolo\TranslationApi\Service\LocaleResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lists the domains of a locale that can be fetched as JSON array, named in
 * full (with `+intl-icu` suffix for ICU domains). The same restrictions as
 * for fetching the messages apply: without full access only exposed domains
 * of allowed locales are listed, with full access all domains of every
 * well-formed locale.
 *
 * As the list depends on the user, the response is always private.
 */
final class TranslationDomainsController
{
    public function __construct(
        private readonly DomainLister $domainLister,
        private readonly LocaleResolver $localeResolver,
        private readonly FullAccessChecker $fullAccessChecker,
        private readonly JsonResponseFactory $responseFactory,
        private readonly ExposedDomains $exposedDomains,
    ) {}

    /**
     * @throws NotFoundHttpException if the locale is malformed or not allowed
     *     and the user has no full access
     */
    #[Route(
        path: '/api/translations/{locale}/domains.{_format}',
        name: 'atoolo_translation_api_domains',
        requirements: ['_format' => 'json'],
        defaults: ['_format' => 'json'],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $locale): Response
    {
        $fullAccess = $this->fullAccessChecker->hasFullAccess();
        $resolvedLocale = $this->localeResolver->resolve($locale, $fullAccess);
        if ($resolvedLocale === null) {
            throw new NotFoundHttpException(sprintf('unsupported locale "%s"', $locale));
        }

        $domains = $this->domainLister->list($resolvedLocale);
        if (!$fullAccess) {
            $domains = array_values(array_filter(
                $domains,
                $this->exposedDomains->isExposed(...),
            ));
        }

        return $this->responseFactory->create($request, $domains, false);
    }
}
