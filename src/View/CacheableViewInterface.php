<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

/**
 * Views implementing this interface have their serialized JSON payload cached
 * automatically when response caching is enabled in the bundle configuration.
 *
 * The signature is the invalidation key: derive it from the source entity or model
 * so it changes whenever the payload would (e.g. id + updated-at timestamp).
 */
interface CacheableViewInterface extends ViewInterface
{
    /**
     * Unique key of the payload state; a new signature means a fresh payload.
     */
    public function getCacheSignature(): string;
}
