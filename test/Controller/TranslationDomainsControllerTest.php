<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Controller;

use Atoolo\TranslationApi\Controller\TranslationDomainsController;
use Atoolo\TranslationApi\Service\DomainLister;
use Atoolo\TranslationApi\Service\FullAccessChecker;
use Atoolo\TranslationApi\Service\JsonResponseFactory;
use Atoolo\TranslationApi\Service\LocaleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslationDomainsController::class)]
class TranslationDomainsControllerTest extends TestCase
{
    private function createController(bool $fullAccess = false): TranslationDomainsController
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['a' => 'A'], 'de', 'frontend+intl-icu');
        $translator->addResource('array', ['a' => 'A'], 'de', 'internal');
        $translator->addResource('array', ['a' => 'A'], 'fr', 'legacy');

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($fullAccess);

        return new TranslationDomainsController(
            new DomainLister($translator),
            new LocaleResolver(['de']),
            new FullAccessChecker($authorizationChecker, 'ROLE_TRANSLATOR'),
            new JsonResponseFactory(600),
            ['frontend', 'empty', 'legacy'],
        );
    }

    public function testListsExposedDomains(): void
    {
        $response = ($this->createController())(new Request(), 'DE');

        $this->assertSame('["frontend+intl-icu"]', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testListsAllDomainsWithFullAccess(): void
    {
        $response = ($this->createController(true))(new Request(), 'de');

        $this->assertSame('["frontend+intl-icu","internal"]', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testFullAccessToLocaleNotAllowed(): void
    {
        $response = ($this->createController(true))(new Request(), 'fr');

        $this->assertSame('["legacy"]', $response->getContent());
    }

    public function testRejectsLocaleNotAllowed(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->createController())(new Request(), 'fr');
    }

    public function testRejectsMalformedLocaleWithFullAccess(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->createController(true))(new Request(), 'de/../fr');
    }
}
