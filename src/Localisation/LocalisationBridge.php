<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Localisation;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Static bridge giving views access to the current request locale, mirroring
 * SecurityBridge: SetLocalisationSubscriber injects the DI-managed request stack
 * here on every request. Views consume it via LocalisationAwareTrait.
 *
 * Holding the request stack service (never a resolved locale) keeps this safe
 * under long-running runtimes: the locale is read from the current request at
 * call time, and the stack is popped by HttpKernel after every request.
 */
final class LocalisationBridge
{
    private static ?RequestStack $requestStack = null;

    private function __construct()
    {
    }

    public static function setRequestStack(?RequestStack $requestStack): void
    {
        self::$requestStack = $requestStack;
    }

    public static function getLocale(): ?string
    {
        return self::$requestStack?->getCurrentRequest()?->getLocale();
    }
}
