<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Controller;

use Atoolo\TranslationApi\Service\CatalogueExporter;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\KeyNester;
use Atoolo\TranslationApi\Service\LocaleResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Delivers the messages of a translation domain as JSON object, as expected
 * by the i18next http backend (`loadPath: '/api/translations/{{lng}}/{{ns}}'`,
 * the `.json` suffix of the i18next default load path is optional).
 * The messages are delivered unchanged, i.e. in ICU message format if the
 * domain uses it.
 *
 * Users with full access (see {@see FullAccessChecker}) may additionally
 * fetch every domain and well-formed locale. These responses must not be
 * stored by shared caches and are therefore private.
 *
 * CORS headers are up to the application (e.g. nelmio/cors-bundle). As they
 * may depend on the `Origin` of the request, the responses vary by it.
 */
final class TranslationController
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        private readonly CatalogueExporter $exporter,
        private readonly LocaleResolver $localeResolver,
        private readonly KeyNester $keyNester,
        private readonly FullAccessChecker $fullAccessChecker,
        private readonly array $domains,
        private readonly int $cacheMaxAge,
    ) {}

    /**
     * @throws NotFoundHttpException if the locale is malformed or the
     *     domain is not exposed or the locale is not allowed and the user
     *     has no full access
     */
    #[Route(
        path: '/api/translations/{locale}/{domain}.{_format}',
        name: 'atoolo_translation_api',
        requirements: ['domain' => '[^/.]+', '_format' => 'json'],
        defaults: ['_format' => 'json'],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $locale, string $domain): Response
    {
        $resolvedLocale = $this->localeResolver->resolve($locale);
        $exposedDomain = in_array($domain, $this->domains, true);
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

        $response = new JsonResponse();
        $response->setEncodingOptions(
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_FORCE_OBJECT,
        );
        $response->setData($messages);
        if ($restricted) {
            $response->setPrivate();
        } else {
            $response->setPublic();
        }
        $response->setMaxAge($this->cacheMaxAge);
        $response->setVary('Origin');
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
