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
 * A BindView whose property binding is deferred until the view is serialized.
 *
 * Subclasses derive the cache signature from the source object statically, so on a
 * cache hit neither binding nor serialization runs — the JSON string is served
 * straight from the pool. ViewNormalizer calls bind() right before reading the
 * properties on a miss.
 *
 *     final class UserView extends CachedBindView
 *     {
 *         public ?int $id = null;
 *         public ?string $name = null;
 *
 *         public static function createCacheSignature(object $source): string
 *         {
 *             \assert($source instanceof User);
 *
 *             return sprintf('user_%d_%d', $source->getId(), $source->getUpdatedAt()->getTimestamp());
 *         }
 *     }
 */
abstract class CachedBindView extends BindView implements CacheableViewInterface, SourceCacheSignatureInterface
{
    private readonly object $bindSource;
    private bool $bindCompleted = false;

    public function __construct(object $object)
    {
        // Intentionally does not call parent::__construct(): binding is deferred
        // until bind() so a cache hit never pays for it
        $this->bindSource = $object;
    }

    final public function getCacheSignature(): string
    {
        return static::createCacheSignature($this->bindSource);
    }

    /**
     * Synchronizes the properties from the source; safe to call repeatedly.
     */
    final public function bind(): void
    {
        if ($this->bindCompleted) {
            return;
        }

        $this->bindCompleted = true;
        static::getBindUtils()->sync($this, $this->bindSource);
    }

    final protected function getSource(): object
    {
        return $this->bindSource;
    }
}
