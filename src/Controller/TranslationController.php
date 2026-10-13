<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Controller;

use Atoolo\TranslationApi\Service\CatalogueExporter;
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
 */
final class TranslationController
{
    /**
     * @param list<string> $domains
     * @param list<string> $corsAllowOrigins
     */
    public function __construct(
        private readonly CatalogueExporter $exporter,
        private readonly LocaleResolver $localeResolver,
        private readonly KeyNester $keyNester,
        private readonly array $domains,
        private readonly int $cacheMaxAge,
        private readonly array $corsAllowOrigins,
    ) {}

    /**
     * @throws NotFoundHttpException if the domain is not exposed or the
     *     locale is malformed or not allowed
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
        if (!in_array($domain, $this->domains, true)) {
            throw new NotFoundHttpException(sprintf('unknown translation domain "%s"', $domain));
        }
        $resolvedLocale = $this->localeResolver->resolve($locale);
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
        $this->addCorsHeaders($request, $response);
        $response->setPublic();
        $response->setMaxAge($this->cacheMaxAge);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function addCorsHeaders(Request $request, Response $response): void
    {
        if ($this->corsAllowOrigins === []) {
            return;
        }
        if (in_array('*', $this->corsAllowOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            return;
        }
        $response->setVary('Origin', false);
        $origin = $request->headers->get('Origin');
        if ($origin !== null && in_array($origin, $this->corsAllowOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
    }
}
