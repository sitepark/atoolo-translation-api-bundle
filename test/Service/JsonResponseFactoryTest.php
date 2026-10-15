<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\JsonResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(JsonResponseFactory::class)]
class JsonResponseFactoryTest extends TestCase
{
    public function testPublicResponse(): void
    {
        $response = (new JsonResponseFactory(600))->create(new Request(), ['ä/ö'], true);

        $this->assertSame('["ä/ö"]', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame(['Origin'], $response->getVary());
        $this->assertNotNull($response->getEtag());
    }

    public function testPrivateResponse(): void
    {
        $response = (new JsonResponseFactory(600))->create(new Request(), [], false);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testEncodingOptions(): void
    {
        $response = (new JsonResponseFactory(600))->create(
            new Request(),
            [],
            true,
            JSON_FORCE_OBJECT,
        );

        $this->assertSame('{}', $response->getContent());
    }

    public function testNotModified(): void
    {
        $factory = new JsonResponseFactory(600);
        $etag = $factory->create(new Request(), ['de'], true)->getEtag();

        $request = new Request();
        $request->headers->set('If-None-Match', (string) $etag);
        $response = $factory->create($request, ['de'], true);

        $this->assertSame(304, $response->getStatusCode());
    }
}
