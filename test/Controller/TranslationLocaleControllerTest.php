<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Controller;

use Atoolo\TranslationApi\Controller\TranslationLocaleController;
use Atoolo\TranslationApi\Service\CatalogueExporter;
use Atoolo\TranslationApi\Service\DomainLister;
use Atoolo\TranslationApi\Service\ExposedDomains;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\JsonResponseFactory;
use Atoolo\TranslationApi\Service\KeyNester;
use Atoolo\TranslationApi\Service\LocaleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslationLocaleController::class)]
class TranslationLocaleControllerTest extends TestCase
{
    /**
     * @param list<string> $domains
     */
    private function createController(
        bool $fullAccess = false,
        array $domains = ['frontend', 'empty', 'legacy'],
    ): TranslationLocaleController {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['a.b' => 'A'], 'de', 'frontend+intl-icu');
        $translator->addResource('array', ['c' => 'C'], 'de', 'frontend');
        $translator->addResource('array', ['a' => 'A'], 'de', 'internal');
        $translator->addResource('array', ['a' => 'A'], 'fr', 'legacy');

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($fullAccess);

        return new TranslationLocaleController(
            new DomainLister($translator),
            new LocaleResolver(['de']),
            new FullAccessChecker($authorizationChecker, ['ROLE_TRANSLATOR']),
            new JsonResponseFactory(600),
            new ExposedDomains($domains),
            new CatalogueExporter($translator),
            new KeyNester(),
        );
    }

    public function testListsExposedDomains(): void
    {
        $response = $this->createController()->domains(new Request(), 'DE');

        $this->assertSame('["frontend+intl-icu"]', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testWildcardListsEveryDomain(): void
    {
        $response = $this->createController(domains: ['*'])->domains(new Request(), 'de');

        $this->assertSame('["frontend+intl-icu","internal"]', $response->getContent());
    }

    public function testListsAllDomainsWithFullAccess(): void
    {
        $response = $this->createController(true)->domains(new Request(), 'de');

        $this->assertSame('["frontend+intl-icu","internal"]', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testFullAccessToLocaleNotAllowed(): void
    {
        $response = $this->createController(true)->domains(new Request(), 'fr');

        $this->assertSame('["legacy"]', $response->getContent());
    }

    public function testRejectsLocaleNotAllowed(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->createController()->domains(new Request(), 'fr');
    }

    public function testRejectsMalformedLocaleWithFullAccess(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->createController(true)->domains(new Request(), 'de/../fr');
    }

    public function testDeliversMessagesOfExposedDomains(): void
    {
        $response = $this->createController()->messages(new Request(), 'de');

        $this->assertSame(
            '{"frontend+intl-icu":{"c":"C","a":{"b":"A"}}}',
            $response->getContent(),
        );
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testDeliversMessagesOfAllDomainsWithFullAccess(): void
    {
        $response = $this->createController(true)->messages(new Request(), 'de');

        $this->assertSame(
            '{"frontend+intl-icu":{"c":"C","a":{"b":"A"}},"internal":{"a":"A"}}',
            $response->getContent(),
        );
    }

    public function testDeliversEmptyObjectWithoutDomains(): void
    {
        $response = $this->createController(domains: [])->messages(new Request(), 'de');

        $this->assertSame('{}', $response->getContent());
    }

    public function testMessagesOfLocaleNotAllowed(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->createController()->messages(new Request(), 'fr');
    }
}
