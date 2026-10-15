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
    public function testOneGrantedRoleIsSufficient(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturnCallback(static fn(string $role): bool => $role === 'ROLE_ADMIN');

        $checker = new FullAccessChecker($authorizationChecker, ['ROLE_API', 'ROLE_ADMIN']);

        $this->assertTrue($checker->hasFullAccess());
    }

    public function testDeniedRoles(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $checker = new FullAccessChecker($authorizationChecker, ['ROLE_API', 'ROLE_ADMIN']);

        $this->assertFalse($checker->hasFullAccess());
    }

    public function testWithoutRoles(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->never())->method('isGranted');

        $checker = new FullAccessChecker($authorizationChecker, []);

        $this->assertFalse($checker->hasFullAccess());
    }

    public function testWithoutSecurity(): void
    {
        $checker = new FullAccessChecker(null, ['ROLE_API']);

        $this->assertFalse($checker->hasFullAccess());
    }
}
