<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Decides whether the current user may fetch every locale and domain,
 * regardless of the configured `locales` and `domains`.
 */
class FullAccessChecker
{
    /**
     * @param AuthorizationCheckerInterface|null $authorizationChecker `null`
     *     if the security bundle is not installed
     * @param list<string> $roles roles (or voter attributes) granting full
     *     access, one of them is sufficient, empty disables full access
     */
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authorizationChecker,
        private readonly array $roles,
    ) {}

    public function hasFullAccess(): bool
    {
        if ($this->authorizationChecker === null) {
            return false;
        }
        foreach ($this->roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }
        return false;
    }
}
