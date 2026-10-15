<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Controller;

use Atoolo\TranslationApi\Controller\TranslationController;
use Atoolo\TranslationApi\Service\CatalogueExporter;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\KeyNester;
use Atoolo\TranslationApi\Service\LocaleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslationController::class)]
class TranslationControllerTest extends TestCase
{
    /**
     * @param list<string> $corsAllowOrigins
     */
    private function createController(
        array $corsAllowOrigins = [],
        bool $fullAccess = false,
    ): TranslationController {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'greeting' => 'Grüße',
            'cart.items' => '{count, plural, one {# Artikel} other {# Artikel}}',
            'link' => 'https://example.com/a/b',
            'html' => '&copy; <b>"Sitepark"</b>',
        ], 'de', 'frontend+intl-icu');
        $translator->addResource('array', [
            'secret' => 'Secret',
        ], 'de', 'internal+intl-icu');
        $translator->addResource('array', [
            'greeting' => 'Bonjour',
        ], 'fr', 'frontend+intl-icu');

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($fullAccess);

        return new TranslationController(
            new CatalogueExporter($translator),
            new LocaleResolver(['de', 'en']),
            new KeyNester(),
            new FullAccessChecker($authorizationChecker, 'ROLE_TRANSLATOR'),
            ['frontend', 'empty'],
            600,
            $corsAllowOrigins,
        );
    }

    public function testDeliversNestedMessages(): void
    {
        $response = ($this->createController())(new Request(), 'de', 'frontend');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            '{"greeting":"Grüße","link":"https://example.com/a/b",'
            . '"html":"&copy; <b>\\"Sitepark\\"</b>",'
            . '"cart":{"items":"{count, plural, one {# Artikel} other {# Artikel}}"}}',
            $response->getContent(),
        );
    }

    public function testSetsCacheHeaders(): void
    {
        $response = ($this->createController())(new Request(), 'de', 'frontend');

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertNotNull($response->getEtag());
    }

    public function testRespondsNotModifiedForMatchingEtag(): void
    {
        $controller = $this->createController();
        $etag = $controller(new Request(), 'de', 'frontend')->getEtag();

        $request = new Request();
        $request->headers->set('If-None-Match', (string) $etag);
        $response = $controller($request, 'de', 'frontend');

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testDeliversEmptyObjectForEmptyCatalogue(): void
    {
        $response = ($this->createController())(new Request(), 'de', 'empty');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{}', $response->getContent());
    }

    public function testRejectsDomainNotExposed(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->createController())(new Request(), 'de', 'internal');
    }

    public function testRejectsLocaleNotAllowed(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->createController())(new Request(), 'fr', 'frontend');
    }

    public function testFullAccessToDomainNotExposed(): void
    {
        $response = ($this->createController(fullAccess: true))(new Request(), 'de', 'internal');

        $this->assertSame('{"secret":"Secret"}', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testFullAccessToLocaleNotAllowed(): void
    {
        $response = ($this->createController(fullAccess: true))(new Request(), 'fr', 'frontend');

        $this->assertSame('{"greeting":"Bonjour"}', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testFullAccessKeepsPublicResponsePublic(): void
    {
        $response = ($this->createController(fullAccess: true))(new Request(), 'de', 'frontend');

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testFullAccessRejectsMalformedLocale(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->createController(fullAccess: true))(new Request(), '../de', 'frontend');
    }

    public function testNoCorsHeadersWithoutConfiguredOrigins(): void
    {
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');
        $response = ($this->createController())($request, 'de', 'frontend');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCorsWildcard(): void
    {
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');
        $response = ($this->createController(['*']))($request, 'de', 'frontend');

        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCorsAllowedOrigin(): void
    {
        $request = new Request();
        $request->headers->set('Origin', 'https://example.com');
        $response = ($this->createController(['https://example.com']))($request, 'de', 'frontend');

        $this->assertSame(
            'https://example.com',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
        $this->assertSame(['Origin'], $response->getVary());
    }

    public function testCorsForeignOrigin(): void
    {
        $request = new Request();
        $request->headers->set('Origin', 'https://evil.example');
        $response = ($this->createController(['https://example.com']))($request, 'de', 'frontend');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertSame(['Origin'], $response->getVary());
    }
}
