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
 * Derives the cache signature automatically from the view's values.
 *
 * The signature is a hash of the serialized view state, so identical values produce
 * a cache hit and any changed value produces a fresh payload — stale responses are
 * impossible by construction. A hit skips normalization and JSON encoding; the view
 * itself is still built.
 *
 * Only use this on views whose values are deterministic for a given source state
 * (no "now" timestamps, counters or randomness — those would make every signature
 * unique and turn the cache into pure overhead) and that hold plain values rather
 * than references to large object graphs (everything the view holds gets serialized
 * into the hash on every request).
 */
// @phpstan-ignore trait.unused (provided for applications; not used inside the bundle sources)
trait AutoCacheSignatureTrait
{
    public function getCacheSignature(): string
    {
        // Hash the property values rather than the object itself so anonymous
        // view classes work too (PHP refuses to serialize anonymous class instances).
        // The class name is included to prevent cross-class collisions.
        return \hash('xxh128', static::class.\serialize(\get_object_vars($this)));
    }
}
