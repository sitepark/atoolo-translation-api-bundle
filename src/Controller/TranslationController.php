<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Controller;

use Atoolo\TranslationApi\Service\CatalogueExporter;
use Atoolo\TranslationApi\Service\DomainName;
use Atoolo\TranslationApi\Service\ExposedDomains;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\JsonResponseFactory;
use Atoolo\TranslationApi\Service\KeyNester;
use Atoolo\TranslationApi\Service\LocaleResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Delivers the messages of a translation domain as JSON object, as expected
 * by the i18next http backend (`loadPath: '/api/translations/{{lng}}/domains/{{ns}}'`,
 * the `.json` suffix of the i18next default load path is optional).
 * The messages are delivered unchanged, i.e. in ICU message format if the
 * domain uses it. The domain may be requested with or without `+intl-icu`
 * suffix, both deliver the ICU and the plain messages of the domain.
 *
 * Users with full access (see {@see FullAccessChecker}) may additionally
 * fetch every domain and well-formed locale. These responses must not be
 * stored by shared caches and are therefore private.
 */
final class TranslationController
{
    public function __construct(
        private readonly CatalogueExporter $exporter,
        private readonly LocaleResolver $localeResolver,
        private readonly KeyNester $keyNester,
        private readonly FullAccessChecker $fullAccessChecker,
        private readonly JsonResponseFactory $responseFactory,
        private readonly ExposedDomains $exposedDomains,
    ) {}

    /**
     * @throws NotFoundHttpException if the locale is malformed or the
     *     domain is not exposed or the locale is not allowed and the user
     *     has no full access
     */
    #[Route(
        path: '/api/translations/{locale}/domains/{domain}.{_format}',
        name: 'atoolo_translation_api',
        requirements: ['domain' => '[^/.]+', '_format' => 'json'],
        defaults: ['_format' => 'json'],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $locale, string $domain): Response
    {
        $domain = DomainName::base($domain);
        $resolvedLocale = $this->localeResolver->resolve($locale);
        $exposedDomain = $this->exposedDomains->isExposed($domain);
        // full access is only checked if needed, so that the responses of
        // public requests stay publicly cacheable
        $restricted = $resolvedLocale === null || !$exposedDomain;
        if ($restricted && $this->fullAccessChecker->hasFullAccess()) {
            $resolvedLocale = $this->localeResolver->resolve($locale, true);
            $exposedDomain = true;
        }
        if (!$exposedDomain) {
            throw new NotFoundHttpException(sprintf('unknown translation domain "%s"', $domain));
        }
        if ($resolvedLocale === null) {
            throw new NotFoundHttpException(sprintf('unsupported locale "%s"', $locale));
        }

        $messages = $this->keyNester->nest(
            $this->exporter->export($resolvedLocale, $domain),
        );

        return $this->responseFactory->create(
            $request,
            $messages,
            !$restricted,
            JSON_FORCE_OBJECT,
        );
    }
}
