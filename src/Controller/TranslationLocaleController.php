<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Controller;

use Atoolo\TranslationApi\Service\CatalogueExporter;
use Atoolo\TranslationApi\Service\DomainLister;
use Atoolo\TranslationApi\Service\DomainName;
use Atoolo\TranslationApi\Service\ExposedDomains;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\JsonResponseFactory;
use Atoolo\TranslationApi\Service\KeyNester;
use Atoolo\TranslationApi\Service\LocaleResolver;
use Atoolo\TranslationApi\Service\TranslationSources;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Delivers the domains of a locale, either as list or together with their
 * messages. The domains are those with messages in the locale or one of its
 * fallback locales, named in full (with `+intl-icu` suffix for ICU domains).
 *
 * The optional query parameter `source` names another source of the
 * messages, see {@see TranslationSources}.
 *
 * The same restrictions as for fetching the messages of a domain apply:
 * without full access only exposed domains of allowed locales, with full
 * access all domains of every well-formed locale. As the result depends on
 * the user, the responses are always private.
 */
final class TranslationLocaleController
{
    public function __construct(
        private readonly DomainLister $domainLister,
        private readonly LocaleResolver $localeResolver,
        private readonly FullAccessChecker $fullAccessChecker,
        private readonly JsonResponseFactory $responseFactory,
        private readonly ExposedDomains $exposedDomains,
        private readonly CatalogueExporter $exporter,
        private readonly KeyNester $keyNester,
        private readonly TranslationSources $sources,
    ) {}

    /**
     * Lists the domains as JSON array.
     *
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
    public function domains(Request $request, string $locale): Response
    {
        [, , $domains] = $this->accessibleDomains($request, $locale);

        return $this->responseFactory->create($request, $domains, false);
    }

    /**
     * Delivers all domains with their messages as JSON object, keyed by the
     * domain. The messages of each domain are the same as when fetched
     * individually.
     *
     * @throws NotFoundHttpException if the locale is malformed or not allowed
     *     and the user has no full access
     */
    #[Route(
        path: '/api/translations/{locale}.{_format}',
        name: 'atoolo_translation_api_locale',
        requirements: ['locale' => '[^/.]+', '_format' => 'json'],
        defaults: ['_format' => 'json'],
        methods: ['GET'],
    )]
    public function messages(Request $request, string $locale): Response
    {
        [$source, $resolvedLocale, $domains] = $this->accessibleDomains($request, $locale);

        $messages = [];
        foreach ($domains as $domain) {
            $messages[$domain] = $this->keyNester->nest(
                $this->exporter->export($source, $resolvedLocale, DomainName::base($domain)),
            );
        }

        return $this->responseFactory->create(
            $request,
            $messages,
            false,
            JSON_FORCE_OBJECT,
        );
    }

    /**
     * @return array{TranslatorBagInterface, string, list<string>} the source,
     *     the resolved locale and its domains the user may fetch
     * @throws NotFoundHttpException if the source is unknown or the locale is
     *     malformed or not allowed and the user has no full access
     */
    private function accessibleDomains(Request $request, string $locale): array
    {
        $source = $this->sources->fromRequest($request);
        $fullAccess = $this->fullAccessChecker->hasFullAccess();
        $resolvedLocale = $this->localeResolver->resolve($locale, $fullAccess);
        if ($resolvedLocale === null) {
            throw new NotFoundHttpException(sprintf('unsupported locale "%s"', $locale));
        }

        $domains = $this->domainLister->list($source, $resolvedLocale);
        if (!$fullAccess) {
            $domains = array_values(array_filter(
                $domains,
                $this->exposedDomains->isExposed(...),
            ));
        }
        return [$source, $resolvedLocale, $domains];
    }
}
