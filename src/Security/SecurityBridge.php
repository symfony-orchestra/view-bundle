<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Static bridge giving views access to the current security user, mirroring the
 * BindView::setBindUtils() pattern: SetSecuritySubscriber injects the DI-managed
 * token storage here on every request. Views consume it via SecurityAwareTrait.
 *
 * Static properties declared in traits are duplicated per using class, so the
 * shared storage has to live in this single holder.
 *
 * Without symfony/security (or before authentication) getUser() returns null.
 */
final class SecurityBridge
{
    private static ?TokenStorageInterface $tokenStorage = null;

    private function __construct()
    {
    }

    public static function setTokenStorage(?TokenStorageInterface $tokenStorage): void
    {
        self::$tokenStorage = $tokenStorage;
    }

    public static function getUser(): ?UserInterface
    {
        return self::$tokenStorage?->getToken()?->getUser();
    }

    public static function getUserIdentifier(): ?string
    {
        return self::getUser()?->getUserIdentifier();
    }
}
