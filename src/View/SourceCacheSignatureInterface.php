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
 * Views that can compute their cache signature from the source object alone,
 * without being constructed.
 *
 * This is what makes cache hits free: CachedView, CachedBindView and
 * #[Type(..., cached: true)] collections resolve the signature via this static
 * method and only build the view when the cache misses.
 */
interface SourceCacheSignatureInterface extends ViewInterface
{
    /**
     * Unique key of the payload state for the given source; a new signature means a fresh payload.
     * Derive it from the source's identity plus a modification marker (e.g. id + updated-at).
     */
    public static function createCacheSignature(object $source): string;
}
