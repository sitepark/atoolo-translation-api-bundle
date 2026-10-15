<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Functional;

use Atoolo\TranslationApi\Dev\Kernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Runs against the application in `dev/` with its translations.
 */
#[CoversNothing]
class TranslationApiTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDeliversTranslationsWithFallback(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en-GB/frontend');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', '*');

        /** @var array{cart: array{checkout: string, items: string}, greeting: string} $data */
        $data = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame('Proceed to till', $data['cart']['checkout']);
        $this->assertSame('Hello from Symfony!', $data['greeting']);
        $this->assertStringStartsWith('{count, plural,', $data['cart']['items']);
    }

    public function testAcceptsJsonSuffix(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/frontend.json');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testRejectsOtherSuffix(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/frontend.xml');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDomainNotExposed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/internal');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testLocaleNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr/frontend');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFullAccessToDomainNotExposed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/internal', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'translator',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            '{"secret":"This domain is not exposed."}',
            $client->getResponse()->getContent(),
        );
        $this->assertTrue(
            $client->getResponse()->headers->hasCacheControlDirective('private'),
        );
    }

    public function testFullAccessToLocaleNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr/frontend', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'translator',
        ]);

        // no French messages, but the fallback locale applies
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'Hello from Symfony!',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testWrongCredentials(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/internal', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'wrong',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testOnlyGetIsAllowed(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/translations/en/frontend');

        $this->assertResponseStatusCodeSame(405);
    }
}
