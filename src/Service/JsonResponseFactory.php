<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates the JSON responses of the API with cache headers and `ETag`.
 *
 * CORS headers are up to the application (e.g. nelmio/cors-bundle). As they
 * may depend on the `Origin` of the request, the responses vary by it.
 */
class JsonResponseFactory
{
    public function __construct(
        private readonly int $cacheMaxAge,
    ) {}

    /**
     * @param bool $public whether shared caches may store the response
     * @param int $encodingOptions additional `json_encode()` flags
     */
    public function create(
        Request $request,
        mixed $data,
        bool $public,
        int $encodingOptions = 0,
    ): Response {
        $response = new JsonResponse();
        $response->setEncodingOptions(
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | $encodingOptions,
        );
        $response->setData($data);
        if ($public) {
            $response->setPublic();
        } else {
            $response->setPrivate();
        }
        $response->setMaxAge($this->cacheMaxAge);
        $response->setVary('Origin');
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
