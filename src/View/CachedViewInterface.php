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
 * A cached-payload descriptor: carries a signature and a recipe for building the
 * actual view, so the view is only constructed when the cache misses.
 *
 * ViewNormalizer resolves any implementation through the per-signature normalized
 * payload cache; ViewSubscriber honors the per-descriptor TTL at the response level.
 */
interface CachedViewInterface extends CacheableViewInterface
{
    /**
     * Cache lifetime in seconds; null uses the configured default.
     */
    public function getTtl(): ?int;

    /**
     * Builds the actual view; called only on a cache miss.
     */
    public function createView(): ViewInterface;
}
