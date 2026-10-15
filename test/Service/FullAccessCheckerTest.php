<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\FullAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(FullAccessChecker::class)]
class FullAccessCheckerTest extends TestCase
{
    public function testGrantedRole(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_TRANSLATOR')
            ->willReturn(true);

        $checker = new FullAccessChecker($authorizationChecker, 'ROLE_TRANSLATOR');

        $this->assertTrue($checker->hasFullAccess());
    }

    public function testDeniedRole(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $checker = new FullAccessChecker($authorizationChecker, 'ROLE_TRANSLATOR');

        $this->assertFalse($checker->hasFullAccess());
    }

    public function testWithoutRole(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->never())->method('isGranted');

        $checker = new FullAccessChecker($authorizationChecker, null);

        $this->assertFalse($checker->hasFullAccess());
    }

    public function testWithoutSecurity(): void
    {
        $checker = new FullAccessChecker(null, 'ROLE_TRANSLATOR');

        $this->assertFalse($checker->hasFullAccess());
    }
}
