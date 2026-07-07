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
 * Pairs a source object with the view class that renders it, deferring both the
 * view construction and the signature to the class itself:
 *
 *     return new CachedView($user, UserView::class);
 *
 * The signature comes from UserView::createCacheSignature($user) — no view instance
 * needed — so on a cache hit nothing is built and nothing is serialized. On a miss
 * the view is created via `new UserView($user)` (or the explicit $factory) when the
 * serializer asks for it.
 *
 * CachedView carries no HTTP status or headers; it is always wrapped in the standard
 * DataView envelope. Use ResponseView/DataView directly when a response needs custom
 * status or headers.
 */
final class CachedView implements CachedViewInterface
{
    /** @var \Closure(): ViewInterface */
    private readonly \Closure $factory;

    /**
     * @param object                                      $source    the entity/model the view renders
     * @param class-string<SourceCacheSignatureInterface> $viewClass view class providing the signature and the default construction
     * @param callable(): ViewInterface|null              $factory   overrides the default `new $viewClass($source)`
     * @param int|null                                    $ttl       cache lifetime in seconds, null uses the configured default
     */
    public function __construct(
        private readonly object $source,
        private readonly string $viewClass,
        ?callable $factory = null,
        private readonly ?int $ttl = null,
    ) {
        // Runtime guard for callers not covered by static analysis
        // @phpstan-ignore function.alreadyNarrowedType
        if (!\is_a($viewClass, SourceCacheSignatureInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf('View class "%s" must implement %s to be used with CachedView.', $viewClass, SourceCacheSignatureInterface::class));
        }

        $this->factory = null !== $factory ? $factory(...) : static fn (): ViewInterface => new $viewClass($source);
    }

    public function getCacheSignature(): string
    {
        return $this->viewClass::createCacheSignature($this->source);
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function createView(): ViewInterface
    {
        return ($this->factory)();
    }
}
