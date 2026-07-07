<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Attribute;

use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Type
{
    /**
     * @param bool $cached wrap each element in a CachedView so its normalized payload
     *                     is cached per item; requires $class to implement SourceCacheSignatureInterface
     */
    public function __construct(
        public string $class,
        public bool $cached = false,
    ) {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist', $class));
        }

        if (!\is_a($class, ViewInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" must implement ViewInterface', $class));
        }

        if ($cached && !\is_a($class, SourceCacheSignatureInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" must implement SourceCacheSignatureInterface to be used with cached: true', $class));
        }
    }
}
