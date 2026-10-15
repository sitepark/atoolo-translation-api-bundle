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
     * @param string|null $role role (or voter attribute) granting full access,
     *     `null` disables full access
     */
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authorizationChecker,
        private readonly ?string $role,
    ) {}

    public function hasFullAccess(): bool
    {
        if ($this->authorizationChecker === null || $this->role === null) {
            return false;
        }
        return $this->authorizationChecker->isGranted($this->role);
    }
}
