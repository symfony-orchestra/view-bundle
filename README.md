[![PHP Composer](https://github.com/chamber-orchestra/view-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/chamber-orchestra/view-bundle/actions/workflows/php.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-brightgreen.svg)](https://phpstan.org/)
[![PHP-CS-Fixer](https://img.shields.io/badge/code%20style-PER--CS%20%2F%20Symfony-blue.svg)](https://cs.symfony.com/)
[![Latest Stable Version](https://img.shields.io/packagist/v/chamber-orchestra/view-bundle.svg)](https://packagist.org/packages/chamber-orchestra/view-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/chamber-orchestra/view-bundle.svg)](https://packagist.org/packages/chamber-orchestra/view-bundle)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777BB4.svg)](https://www.php.net/)
[![Symfony 8.0](https://img.shields.io/badge/Symfony-8.0-000000.svg)](https://symfony.com/)

# ChamberOrchestra View Bundle

A Symfony bundle that provides a **typed view layer for JSON API responses**. Define response shapes as PHP classes, return them from controllers, and let the bundle handle serialization automatically — no manual `JsonResponse` construction needed.

Built for **Symfony 8.0** and **PHP 8.5+**, the bundle eliminates boilerplate in REST API controllers by introducing view models with automatic property binding, collection mapping, and production-ready cache warming.

## Key Features

- **Typed view models** — define JSON response structures as PHP classes with typed properties
- **Automatic property binding** — `BindView` maps domain object properties to view properties via reflection
- **Collection mapping** — `IterableView` transforms arrays and iterables with typed element views
- **Null stripping** — null values are automatically excluded from serialized JSON output
- **Build-time cache warming** — pre-computed metadata and property mappings eliminate reflection overhead in production
- **Build-versioned caching** — cache files are tied to `container.build_id` for zero-downtime deployments
- **Doctrine proxy support** — transparent lazy-load initialization before property access

## Requirements

- PHP 8.5+
- Symfony 8.0 components (http-kernel, serializer, property-access, dependency-injection, config, framework-bundle)
- doctrine/common ^3.5

## Installation

```bash
composer require chamber-orchestra/view-bundle:8.0.*
```

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    ChamberOrchestra\ViewBundle\ChamberOrchestraViewBundle::class => ['all' => true],
];
```

## Quick Start

Define a view model that maps properties from a domain object:

```php
use ChamberOrchestra\ViewBundle\Attribute\BindsFrom;
use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\IterableView;

#[BindsFrom(User::class)]
final class UserView extends BindView
{
    public string $id;
    public string $name;

    #[Type(ImageView::class)]
    public IterableView $images;

    public function __construct(User $user)
    {
        parent::__construct($user);
    }
}

final class ImageView extends BindView
{
    public string $path;
}
```

Return the view from a controller — the bundle converts it to a `JsonResponse` automatically:

```php
#[Route('/user/me', methods: ['GET'])]
final class GetMeAction
{
    public function __invoke(): UserView
    {
        return new UserView($this->getUser());
    }
}
```

`ViewSubscriber` converts any `ViewInterface` result into a `JsonResponse`. Non-view results pass through unchanged.

## View Types

| View | Purpose |
|------|---------|
| `ResponseView` | Base response with HTTP status (200) and `Content-Type: application/json` headers |
| `DataView` | Wraps any view or array under a `"data"` key |
| `BindView` | Maps matching properties from a source object using reflection |
| `IterableView` | Maps collections via a callback or view class string |
| `KeyValueView` | Produces associative array output for metadata blocks |
| `CachedView` | Descriptor pairing a source object with its view class; skips view building and serialization on a hit |
| `PrivateCachedView` | A `CachedView` scoped to the current user and request locale — private payloads get an isolated cache entry per viewing context |
| `CachedBindView` | A `BindView` with deferred binding and a source-derived signature; skips binding and serialization on a hit |

### BindView Property Binding

`BindView` uses `BindUtils` to synchronize properties between source objects and view instances. It handles:

- Built-in PHP types and custom objects
- `ViewInterface` subclasses (auto-constructed)
- `IterableView` properties with `#[Type(ViewClass::class)]` attribute for typed collections
- Skips union types and incompatible type pairs

### Cached JSON Responses

Response caching is controlled by bundle configuration (all values below are the defaults):

```yaml
# config/packages/chamber_orchestra_view.yaml
chamber_orchestra_view:
    response_cache:
        enabled: true        # master switch for all response caching
        pool: cache.app      # PSR-6 pool service id storing the JSON payloads
        default_ttl: 86400   # seconds (one day); every entry expires — null is not allowed
```

A JSON payload cache always needs an invalidation key, so views opt in by providing a **signature** — a string that changes whenever the payload would. The core building block is `SourceCacheSignatureInterface`: a static method computing the signature from the source object, so no view has to be built to answer "is this payload cached?".

```php
public static function createCacheSignature(object $source): string
{
    \assert($source instanceof User);

    return sprintf('user_%d_%d', $source->getId(), $source->getUpdatedAt()->getTimestamp());
}
```

The ways to opt in:

**1. Implement `CacheableViewInterface`** — the view declares its own signature and every controller returning it gets caching automatically:

```php
final class UserView extends BindView implements CacheableViewInterface
{
    public ?int $id = null;
    public ?string $name = null;

    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
    }

    public function getCacheSignature(): string
    {
        return sprintf('user_%d_%d', $this->user->getId(), $this->user->getUpdatedAt()->getTimestamp());
    }
}
```

On a repeated request the serialization (normalize + `json_encode`) is skipped and the JSON comes from the pool. The view object itself is still constructed by the controller.

If there is no convenient entity marker to build a signature from, `AutoCacheSignatureTrait` derives it automatically from the view's values (a hash of the class name + property values):

```php
final class UserView extends BindView implements CacheableViewInterface
{
    use AutoCacheSignatureTrait;

    public ?int $id = null;
    public ?string $name = null;
}
```

Identical values hit the cache; any changed value produces a fresh payload, so stale responses are impossible by construction. Two caveats: the view's values must be deterministic for a given source state (a "now" timestamp or random value makes every signature unique and turns the cache into pure overhead), and since the values must exist before they can be hashed, the view is fully built on every request — only the serialization step is saved (~15-20% of the request pipeline for a 100-item collection; see `CachedViewBench`). When the source data offers an id + modification marker, `CachedBindView` or `CachedView` save much more.

**2. Extend `CachedBindView`** — the recommended option for `BindView`-based views. Binding is deferred until the view is actually serialized, and the signature comes from `createCacheSignature()`, so a cache hit skips both binding and serialization while the controller keeps returning the view directly:

```php
final class UserView extends CachedBindView
{
    public ?int $id = null;
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof User);

        return sprintf('user_%d_%d', $source->getId(), $source->getUpdatedAt()->getTimestamp());
    }
}

// controller — unchanged BindView ergonomics
return new UserView($user);
```

`ViewNormalizer` triggers the deferred binding on a cache miss (or whenever the view is serialized outside the cache flow, including nested in other views), so an unbound view can never leak empty payloads.

**3. Return a `CachedView`** — a descriptor pairing a source object with the view class that renders it. On a hit nothing is built; on a miss the view is created via `new UserView($user)` (or an explicit factory) when the serializer asks for it:

```php
public function show(User $user): CachedView
{
    return new CachedView($user, UserView::class);          // UserView implements SourceCacheSignatureInterface
    // or with an explicit factory / TTL:
    return new CachedView($user, UserView::class, fn (): ViewInterface => new UserView($user), ttl: 3600);
}
```

`CachedView` carries no HTTP status or headers — it always renders in the standard `DataView` envelope with a 200 response. Use `ResponseView`/`DataView` directly when a response needs custom status or headers.

**4. Return a `PrivateCachedView`** — for private payloads: responses that vary per viewing context, in the spirit of `Cache-Control: private`. The current security user's identity **and** the current request locale become part of the signature, so every user gets an isolated cache entry per locale and can never be served another user's or another locale's payload. Neither is passed around — views become context-aware through static bridges, the same pattern `BindView` uses for `BindUtils`:

```php
final class ArticleView extends View implements SourceCacheSignatureInterface
{
    use LocalisationAwareTrait;
    use SecurityAwareTrait;

    public int $id;
    public string $title;
    public bool $canEdit;

    public function __construct(Article $article)
    {
        $this->id = $article->getId();
        $this->title = $article->getTitle(self::getLocale() ?? 'en');
        $this->canEdit = self::getUserIdentifier() === $article->getAuthorIdentifier();
    }

    public static function createCacheSignature(object $source): string { /* id + updated-at */ }
}

// controller
public function show(Article $article): PrivateCachedView
{
    return new PrivateCachedView($article, ArticleView::class);
}
```

Under the hood, `SetSecuritySubscriber` injects the token storage into `SecurityBridge` and `SetLocalisationSubscriber` injects the request stack into `LocalisationBridge` on every request (mirroring `SetVersionSubscriber`/`BindUtils`); `SecurityAwareTrait` / `LocalisationAwareTrait` expose them to views as `self::getUser()`, `self::getUserIdentifier()` and `self::getLocale()`. Without `symfony/security` (or before authentication) the user resolves to a single shared `anonymous` entry; without a request (CLI) the locale resolves to a `default` entry. Private entries multiply per user × locale × entity; they expire after the configured `default_ttl` (one day by default), and a shorter per-descriptor TTL is worth setting for high-cardinality endpoints. `PrivateCachedView` also works as a collection entry, exactly like `CachedView`. For custom scoping (only user, only locale, tenant id, …), put the relevant aware-traits directly into the view's `createCacheSignature()` and return a plain `CachedView`.

The bridges are safe under long-running runtimes (FrankenPHP worker mode, RoadRunner, Swoole): they hold *services* (token storage, request stack), never a resolved user or locale, and resolve at call time. Symfony resets the token storage and pops the request stack between worker requests, so nothing can leak into the next request even though the static references survive.

**5. Cache collection items with `#[Type(..., cached: true)]`** — inside an `IterableView`-typed property, each element becomes a `CachedView` descriptor and its normalized payload is cached per item:

```php
final class UserListView extends BindView
{
    #[Type(UserView::class, cached: true)]  // UserView implements SourceCacheSignatureInterface
    public ?IterableView $users = null;
}
```

Unchanged items are served from the per-item cache and only new or modified entities are bound and normalized — a page mixing cached and fresh items pays only for the fresh ones. The final `json_encode` over the assembled payload still runs per request. For personalised collection items, map elements to `PrivateCachedView` explicitly:

```php
new IterableView($articles, fn (object $a): PrivateCachedView => new PrivateCachedView($a, ArticleView::class));
```

Setting `response_cache.enabled: false` (e.g. in `config/packages/dev/`) turns all response caching into a transparent pass-through — everything is built and serialized on every request.

Whether caching pays off depends on the pool and the payload: a hit costs a pool lookup, flat in payload size, so cache large or expensive payloads — a tiny 5-property payload rebuilds faster than any out-of-process pool can answer. See the [benchmark results](#results) below for the full comparison across pools and caching modes.

## Architecture

### Request/Response Flow

1. **SetVersionSubscriber** (priority 256) — injects the DI-managed `BindUtils` instance into `BindView` via `BindView::setBindUtils()`
2. Controller returns a `ViewInterface` object
3. **ViewSubscriber** — detects `ViewInterface` results, wraps non-`ResponseViewInterface` in `DataView`, serializes to JSON via `ViewNormalizer`. `CachedView` results are resolved through `ViewResponseCache` first; on a hit the cached JSON is returned without building or serializing the view

### View Auto-Discovery

Views implementing `ViewInterface` are automatically tagged with `chamber_orchestra.view` via `#[AutoconfigureTag]`. The `ViewPass` compiler pass collects these classes and passes them to cache warmers for pre-computation.

## Performance Optimizations

The bundle includes a two-phase optimization strategy for production environments:

### Phase 1: Runtime Metadata Caching

- `ViewMetadataFactory` caches property metadata in memory
- `BindUtils` binds through `ReflectionProperty` objects and cached getter resolution instead of the PropertyAccessor machinery (~3.5× faster property binding)
- Doctrine proxies are initialized once per sync, not per property access
- 30-50% faster normalization on repeated calls

### Phase 2: Build-Time Cache Warming

- `ViewMetadataCacheWarmer` pre-computes view property metadata at build time
- `BindUtilsCacheWarmer` pre-computes property mappings (uses `#[BindsFrom]` for targeted source classes, falls back to N² pairs)
- Generated opcache-optimized PHP files stored in `kernel.share_dir`
- Cache files are versioned with `container.build_id` for safe deployments
- 60-80% reduction in reflection overhead on production requests
- Automatic fallback to reflection when warmed cache is unavailable

### Cache Configuration

`BindUtils` is registered as a DI service with `$buildId`, `$debug`, and `$shareDir` constructor arguments. When `APP_DEBUG=false`, property accessor caching is enabled with a 24-hour lifetime. `SetVersionSubscriber` injects the configured instance into `BindView` on each request.

**Warm the cache in production:**

```bash
bin/console cache:warmup --env=prod
```

This generates build-versioned files in the shared cache directory:
- View property metadata (nullability, defaults, types)
- View-to-view property mappings for `BindUtils`

### Response-Level JSON Caching

Signature-based caching (see above) short-circuits the pipeline for payloads whose state is identifiable: `CacheableViewInterface` skips normalization and `json_encode`; `CachedBindView` and `CachedView` additionally skip property binding / view construction. `ViewResponseCache` stores the final JSON string in the configured PSR-6 pool (`response_cache.pool`, default `cache.app`); when disabled or without a pool it degrades to a transparent pass-through.

## Benchmarks

PHPBench benchmarks are included to measure serialization performance and cache impact:

```bash
composer bench                               # Run all benchmarks
vendor/bin/phpbench run --report=default     # Run with default report
```

Benchmark classes: `BindUtilsBench`, `CacheWarmupBench`, `NormalizationBench`, `CachedViewBench`.

An optional Redis benchmark (`CachedViewRedisProfile`) is excluded from the default run and requires a reachable Redis server (`redis://127.0.0.1:6379` by default, override via `CACHED_VIEW_BENCH_REDIS_DSN`):

```bash
vendor/bin/phpbench run benchmark/CachedViewRedisProfile.php --report=aggregate
```

### Results

Reference numbers from an Apple Silicon workstation (PHP 8.5 CLI, `--php-disable-ini`, so no opcache/xdebug — treat them as relative comparisons, not absolutes).

**Property binding** (`BindUtilsBench`, 5-property `BindView` per construction):

| Scenario | Time |
|---|---|
| Bind from public-property source | 4.3μs |
| Bind from private properties + getters (Doctrine style) | 4.4μs |
| Bind from private properties without getters | 4.8μs |
| All target properties already populated (skip path) | 1.4μs |

**Normalization** (`NormalizationBench`):

| Scenario | Time |
|---|---|
| Normalize 3-property view | 0.7μs |
| Normalize 10-property view | 1.8μs |
| Serialize (normalize + `json_encode`) 3-property view | 1.3μs |
| Serialize 10-property view | 2.5μs |

**Response caching** (`CachedViewBench` / `CachedViewRedisProfile`) — small payload is a 5-property view, large is a 100-item collection bound via `#[Type]`:

| Scenario | Small payload | 100-item collection |
|---|---|---|
| Full pipeline, no cache | 6.8μs | 544μs |
| `CachedView` / `CachedBindView` hit, in-memory pool | 0.8μs | 0.6μs |
| `PrivateCachedView` hit (user + locale scoped), in-memory pool | 1.1μs | 1.2μs |
| `CachedView` / `CachedBindView` hit, filesystem pool | 16.4μs | 17.3μs |
| `PrivateCachedView` hit, filesystem pool | 17.0μs | 18.4μs |
| `CachedView` hit, Redis pool (loopback) | 41.7μs | 43.1μs |
| Per-item cache only (`#[Type(cached: true)]`), in-memory pool | — | 116μs |
| `AutoCacheSignatureTrait` hit (view still built), in-memory pool | — | 452μs |

Takeaways: a cache hit costs the pool lookup, flat in payload size, so caching wins once building + serializing exceeds it — dramatically for collections (~900× in-memory, ~30× filesystem, ~13× Redis on loopback), while a tiny payload rebuilds faster (6.8μs) than any out-of-process pool answers. `PrivateCachedView` adds ~0.3μs over `CachedView` for resolving the user and locale from the bridges. Per-item caching keeps item-level invalidation at a middle-ground cost, and value-hash signatures (`AutoCacheSignatureTrait`) only save the serialization slice since the view must be built to be hashed.

## Development

```bash
composer install          # Install dependencies
composer test             # Run all tests (172 tests, 543 assertions)
./bin/phpunit             # Run tests directly
./bin/phpunit --filter X  # Run specific test class or method
```

## License

MIT
