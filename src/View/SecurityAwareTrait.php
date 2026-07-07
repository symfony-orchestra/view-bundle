<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Gives views static access to the current security user without passing it
 * through constructors — usable in __construct() for personalised fields and in
 * static createCacheSignature() for user-scoped signatures.
 *
 * The user is injected per request by SetSecuritySubscriber via SecurityBridge;
 * both accessors return null when no user is authenticated (or security is absent).
 */
trait SecurityAwareTrait
{
    protected static function getUser(): ?UserInterface
    {
        return SecurityBridge::getUser();
    }

    protected static function getUserIdentifier(): ?string
    {
        return SecurityBridge::getUserIdentifier();
    }
}
