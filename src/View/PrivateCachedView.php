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
 * A CachedView for private payloads — responses that vary per viewing context,
 * in the spirit of Cache-Control "private". The current security user's identity
 * and the current request locale (both resolved through the static bridges filled
 * per request by SetSecuritySubscriber / SetLocalisationSubscriber) become part
 * of the cache signature, so every user gets an isolated entry per locale and can
 * never be served another user's or another locale's payload.
 *
 *     return new PrivateCachedView($article, ArticleView::class);
 *
 * The view class itself can read the same context — use SecurityAwareTrait and
 * LocalisationAwareTrait in its constructor for personalised and localised fields
 * — so nothing has to be passed around. Unauthenticated requests share the
 * "anonymous" entries; requests without a locale (CLI) share the "default" ones.
 *
 * Private entries multiply per user × locale × entity; they expire after the
 * configured default TTL (one day) — set a shorter per-descriptor TTL for
 * high-cardinality endpoints.
 */
final class PrivateCachedView implements CachedViewInterface
{
    use LocalisationAwareTrait;
    use SecurityAwareTrait;

    private const string ANONYMOUS_IDENTITY = 'anonymous';
    private const string DEFAULT_LOCALE_ENTRY = 'default';

    private readonly CachedView $inner;

    /**
     * @param object                                      $source    the entity/model the view renders
     * @param class-string<SourceCacheSignatureInterface> $viewClass view class providing the signature and the default construction
     * @param callable(): ViewInterface|null              $factory   overrides the default `new $viewClass($source)`
     * @param int|null                                    $ttl       cache lifetime in seconds, null uses the configured default
     */
    public function __construct(
        object $source,
        string $viewClass,
        ?callable $factory = null,
        ?int $ttl = null,
    ) {
        $this->inner = new CachedView($source, $viewClass, $factory, $ttl);
    }

    public function getCacheSignature(): string
    {
        return $this->inner->getCacheSignature()
            .'@user:'.(self::getUserIdentifier() ?? self::ANONYMOUS_IDENTITY)
            .'@locale:'.(self::getLocale() ?? self::DEFAULT_LOCALE_ENTRY);
    }

    public function getTtl(): ?int
    {
        return $this->inner->getTtl();
    }

    public function createView(): ViewInterface
    {
        return $this->inner->createView();
    }
}
