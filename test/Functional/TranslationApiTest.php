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
        $client->request('GET', '/api/translations/en-GB/domains/frontend', server: [
            'HTTP_ORIGIN' => 'https://example.com',
        ]);

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
        $client->request('GET', '/api/translations/de/domains/frontend.json');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testRejectsOtherSuffix(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/domains/frontend.xml');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDomainNotExposed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/domains/internal');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testLocaleNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr/domains/frontend');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFullAccessToDomainNotExposed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/domains/internal', server: [
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
        $client->request('GET', '/api/translations/fr/domains/frontend', server: [
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

    public function testListsExposedDomains(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/domains');

        $this->assertResponseIsSuccessful();
        $this->assertSame('["frontend+intl-icu"]', $client->getResponse()->getContent());
    }

    public function testListsDomainsOfLocaleNotAllowedWithFullAccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr/domains.json', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'translator',
        ]);

        // the domains of the fallback locale "en" are included
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            '["frontend+intl-icu","internal+intl-icu","legacy"]',
            $client->getResponse()->getContent(),
        );
    }

    public function testDomainListOfLocaleNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr/domains');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFetchesListedIcuDomain(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/domains/frontend+intl-icu');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('"greeting"', (string) $client->getResponse()->getContent());
    }

    public function testDeliversAllExposedDomainsOfLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en-GB');

        $this->assertResponseIsSuccessful();
        /** @var array<string, array{cart: array{checkout: string}}> $data */
        $data = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame(['frontend+intl-icu'], array_keys($data));
        $this->assertSame('Proceed to till', $data['frontend+intl-icu']['cart']['checkout']);
    }

    public function testDeliversAllDomainsOfLocaleWithFullAccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/fr.json', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'translator',
        ]);

        $this->assertResponseIsSuccessful();
        /** @var array<string, array<string, mixed>> $data */
        $data = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame(['frontend+intl-icu', 'internal+intl-icu', 'legacy'], array_keys($data));
        $this->assertSame(['farewell' => 'Au revoir'], $data['legacy']);
    }

    public function testDeliversMessagesOfSource(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/domains/frontend?source=overrides');

        $this->assertResponseIsSuccessful();
        $this->assertSame('{"greeting":"Moin!"}', $client->getResponse()->getContent());
    }

    public function testUnknownSource(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/de/domains/frontend?source=unknown');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCorsPreflight(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/translations/en/domains/internal', server: [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        $this->assertResponseHeaderSame('Access-Control-Allow-Headers', 'authorization');
    }

    public function testWrongCredentials(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/translations/en/domains/internal', server: [
            'PHP_AUTH_USER' => 'translator',
            'PHP_AUTH_PW' => 'wrong',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testOnlyGetIsAllowed(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/translations/en/domains/frontend');

        $this->assertResponseStatusCodeSame(405);
    }
}
