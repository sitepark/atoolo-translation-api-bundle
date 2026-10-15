<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\DomainName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainName::class)]
class DomainNameTest extends TestCase
{
    public function testBaseOfIcuDomain(): void
    {
        $this->assertSame('frontend', DomainName::base('frontend+intl-icu'));
    }

    public function testBaseOfPlainDomain(): void
    {
        $this->assertSame('frontend', DomainName::base('frontend'));
    }

    public function testIcu(): void
    {
        $this->assertSame('frontend+intl-icu', DomainName::icu('frontend'));
        $this->assertSame('frontend+intl-icu', DomainName::icu('frontend+intl-icu'));
    }
}
