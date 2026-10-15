<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\ExposedDomains;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExposedDomains::class)]
class ExposedDomainsTest extends TestCase
{
    public function testListedDomain(): void
    {
        $exposed = new ExposedDomains(['frontend']);

        $this->assertTrue($exposed->isExposed('frontend'));
        $this->assertTrue($exposed->isExposed('frontend+intl-icu'));
        $this->assertFalse($exposed->isExposed('internal'));
    }

    public function testListedIcuDomain(): void
    {
        $exposed = new ExposedDomains(['frontend+intl-icu']);

        $this->assertTrue($exposed->isExposed('frontend'));
    }

    public function testNothingExposed(): void
    {
        $this->assertFalse((new ExposedDomains([]))->isExposed('frontend'));
    }

    public function testWildcardExposesEveryDomain(): void
    {
        $exposed = new ExposedDomains(['*']);

        $this->assertTrue($exposed->isExposed('frontend'));
        $this->assertTrue($exposed->isExposed('internal+intl-icu'));
    }
}
