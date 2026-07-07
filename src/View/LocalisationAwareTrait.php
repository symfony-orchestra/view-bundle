<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;

/**
 * Gives views static access to the current request locale without passing it
 * through constructors — usable in __construct() for localised fields and in
 * static createCacheSignature() for locale-scoped signatures.
 *
 * The locale is injected per request by SetLocalisationSubscriber via
 * LocalisationBridge; getLocale() returns null outside a request (CLI, workers
 * between requests).
 */
trait LocalisationAwareTrait
{
    protected static function getLocale(): ?string
    {
        return LocalisationBridge::getLocale();
    }
}
