# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ChamberOrchestra View Bundle is a Symfony 8.0 bundle that provides a typed, reusable view layer for building JSON responses. Controllers return `ViewInterface` objects instead of `Response`; the bundle's event subscriber handles serialization to `JsonResponse` automatically.

**Requirements**: PHP 8.5+, Symfony 8.0 components, doctrine/common ^3.5

## Commands

```bash
composer install                        # Install dependencies
vendor/bin/phpunit                      # Run all tests (unit + integration)
vendor/bin/phpunit --filter ClassName   # Run a specific test class
vendor/bin/phpunit --filter testMethodName # Run a specific test method
composer test                           # Alias for vendor/bin/phpunit
composer analyse                        # Run PHPStan static analysis
composer cs-check                       # Check code style (dry-run)
composer cs-fix                         # Fix code style
composer bench                          # Run PHPBench benchmarks
php -l path/to/File.php                 # Quick syntax check
```

## Architecture

### View Hierarchy

The core abstraction is `ViewInterface` (marker interface). `ResponseViewInterface` defines `getStatus()` and `getHeaders()` for views that control HTTP response details. Views compose into JSON responses:

- **View** — abstract base class implementing `ViewInterface`; convenience superclass for custom views
- **ResponseView** — base with status code (200) and JSON headers; implements `ResponseViewInterface` and `NormalizableInterface`
- **DataView** — wraps any `ViewInterface` or array under a `"data"` key; implements `ResponseViewInterface`
- **BindView** — extends `stdClass`; maps matching properties from a source domain object using reflection via `BindUtils::sync()`. Uses a static `setBindUtils()`/`getBindUtils()` bridge to receive the DI-managed `BindUtils` instance (falls back to `new BindUtils()` without DI). The `#[BindsFrom(EntityClass::class)]` attribute declares source classes for targeted cache warming. The `#[Type(ViewClass::class)]` attribute on `IterableView` properties specifies element view classes
- **IterableView** — maps collections via callback or view class string
- **KeyValueView** — produces associative array output for metadata blocks
- **SourceCacheSignatureInterface** — extends `ViewInterface`; declares `static createCacheSignature(object $source): string` so signatures are computable from the source without constructing the view. Required by `CachedView`, `CachedBindView` and `#[Type(..., cached: true)]`
- **CachedViewInterface** — extends `CacheableViewInterface` with `getTtl()` and `createView()`; the contract `ViewNormalizer` resolves through the per-signature normalized-payload cache. Implemented by `CachedView` and `PrivateCachedView`
- **CachedView** — final descriptor pairing a source object with the view class rendering it (`new CachedView($user, UserView::class, ?factory, ?ttl)`); default factory is `new $viewClass($source)`. Carries NO status/headers (always the standard `DataView` envelope, use `ResponseView` for custom responses)
- **PrivateCachedView** — private-payload `CachedView` (composition over an inner `CachedView`): the current user's identifier and the request locale — resolved via `SecurityBridge`/`LocalisationBridge`, never passed in — are appended to the signature (`...@user:<identifier|anonymous>@locale:<locale|default>`), isolating cache entries per user × locale. For custom scoping, views use the aware-traits inside their own `createCacheSignature()` with a plain `CachedView`
- **SecurityAwareTrait** — gives views static access to the current user (`self::getUser()` / `self::getUserIdentifier()`), usable in constructors for personalised fields and in `createCacheSignature()`. Delegates to `SecurityBridge` (`src/Security/`), a static holder filled per request by `SetSecuritySubscriber` — same pattern as `BindView::setBindUtils()`. Trait statics are per-using-class in PHP, hence the single shared holder. symfony/security-core is a soft dependency (dev-only + composer suggest); without it everything resolves to the anonymous user. Worker-mode safe (FrankenPHP/RoadRunner/Swoole): the bridge stores the token storage SERVICE (reset between requests by the framework's services resetter), never a resolved user — do not change it to cache the user object statically
- **LocalisationAwareTrait / LocalisationBridge / SetLocalisationSubscriber** — the identical bridge pattern for the request locale: the bridge (`src/Localisation/`) holds the framework `RequestStack` service and resolves `getLocale()` from the current request at call time. No extra dependencies (http-foundation is already required). Same worker-mode rule: store the stack service, never a resolved locale
- **CacheableViewInterface** — extends `ViewInterface`; views implementing `getCacheSignature()` get their serialized JSON cached automatically by `ViewSubscriber` (the view is still constructed; only normalization + encoding are skipped). `CachedView` implements it
- **AutoCacheSignatureTrait** — provides `getCacheSignature()` as a hash of class name + `get_object_vars()`, for views without an entity marker to derive a signature from. Values must be deterministic; works with anonymous classes (hashes values, not the object)
- **CachedBindView** — abstract `BindView` + `CacheableViewInterface` + `SourceCacheSignatureInterface` with deferred binding: the constructor only stores the source (does NOT call `parent::__construct()`); `ViewNormalizer` calls `bind()` right before reading properties, so a cache hit skips binding entirely. Subclasses implement the static `createCacheSignature()`; the instance `getCacheSignature()` delegates to it. `BindView::getBindUtils()` is `protected static` to support this
- The `#[Type(ViewClass::class, cached: true)]` attribute variant makes `BindUtils` map each collection element to a `CachedView` descriptor, enabling per-item normalized-payload caching inside `IterableView` properties

### Request/Response Flow

1. **SetVersionSubscriber** (priority 256, early) — on `RequestEvent`, calls `BindView::setBindUtils()` to inject the DI-managed `BindUtils` instance into the static bridge. **SetSecuritySubscriber** and **SetLocalisationSubscriber** (same priority) do the same for the security token storage (`SecurityBridge::setTokenStorage()`, optional — null without symfony/security) and the request stack (`LocalisationBridge::setRequestStack()`)
2. Controller returns a `ViewInterface` object
3. **ViewSubscriber** — on `ViewEvent`, detects `ViewInterface` results, wraps non-`ResponseViewInterface` in `DataView`, serializes to JSON, and sets status/headers. `CachedView` results are resolved through `ViewResponseCache` (PSR-6, `cache.app` by default) before any view building or serialization happens

### Property Binding (BindUtils)

`BindUtils` is a DI service that synchronizes properties between source objects and BindView instances. Configured via constructor (`$buildId`, `$debug`, `$shareDir`) and registered in the container by `services.php`. It uses reflection to find intersecting properties, validates type compatibility, and handles:
- Built-in types, custom objects, ViewInterface subclasses (auto-constructed), IterableView with `#[Type]` attribute
- Skips union types and incompatible types
- The per-property hot path uses the cached `ReflectionProperty` objects directly: public getters (`get`/`is`/`has` prefixes, resolved once per class into `$getterCache`) take priority, then direct reflection reads/writes. Doctrine proxies are initialized once per `sync()`. The Symfony PropertyAccessor is only a fallback for non-public target properties (setter support)
- Property accessor caching enabled when `$buildId` is non-empty; 24h lifetime in production (`$debug=false`), disabled in debug mode
- Exposes `isReflectionTypeValidForInitialization()`, `isView()`, and `isAutoConfigurableType()` as `public static` utility methods (shared with `BindUtilsCacheWarmer`)

### Response-Level JSON Caching

`ViewResponseCache` (`src/Cache/`) stores two kinds of payloads keyed by hashed view signatures in a PSR-6 pool: whole-response JSON strings (`get()`, used by `ViewSubscriber`) and per-item normalized data (`getNormalized()`, used by `ViewNormalizer` for `CachedView` descriptors) under separate key namespaces. Configured via the `response_cache` bundle config (`Configuration` + `ChamberOrchestraViewExtension`): `enabled` (default true, master switch), `pool` (default `cache.app`, wired with `NULL_ON_INVALID_REFERENCE`), `default_ttl` (default `ViewResponseCache::DEFAULT_TTL_SECONDS` = 86400, one day; must be a positive integer — null/non-expiring entries are not allowed; `CachedView` descriptors may override per instance). Every stored entry gets `expiresAfter()`. When disabled or without a pool it is a transparent pass-through. Signatures are derived from the source entity/model (e.g. id + updated-at timestamp) so a state change produces a new signature.

### Doctrine Integration

`ReflectionPropertyAccessor` decorates Symfony's PropertyAccessor and initializes Doctrine proxy objects before accessing their properties.

### Serializer & Metadata

`ViewNormalizer` handles `ViewInterface` instances, strips null values from output, and delegates nested normalization. It uses `ViewMetadataFactory` to introspect view classes. It resolves `CachedView` descriptors through `ViewResponseCache::getNormalized()` (building the view only on a miss) and triggers the deferred binding of `CachedBindView` instances before reading their properties.

- **ViewMetadataFactory** — builds `ViewClassMetadata` for view classes; supports warm cache loading from pre-exported PHP files
- **ViewClassMetadata** / **ViewPropertyMetadata** — value objects describing view class structure (property names, nullability, default values)

### Cache Warming

Two cache warmers pre-compute reflection data at deploy time, writing exported PHP files to `kernel.share_dir`:

- **BindUtilsCacheWarmer** — pre-computes property intersection mappings for `BindUtils`. Uses `#[BindsFrom]` attributes on target views to limit pairs to declared source classes; falls back to N² view×view pairs when no attribute is present
- **ViewMetadataCacheWarmer** — pre-computes serialization metadata for `ViewNormalizer`

**ViewPass** (compiler pass) collects classes tagged `chamber_orchestra.view` and passes them to both cache warmers.

## Code Conventions

- PSR-12 style, `declare(strict_types=1)` in every file, 4-space indent
- View classes end with `View` suffix; utilities use verb naming (`BindUtils`, `ReflectionService`)
- Typed properties and return types; favor `readonly` where appropriate
- JSON structures should be explicit — avoid leaking nulls
- Namespace: `ChamberOrchestra\ViewBundle\*` (PSR-4 from `src/`)
- Follow a consistent formatting style.
- Use clear, descriptive names for variables, functions, and classes.
- Avoid non-standard abbreviations.
- Each function should have a single, well-defined responsibility.

## Testing

- PHPUnit 13.x; tests in `tests/` autoloaded as `Tests\`
- **Unit tests** (`tests/Unit/`) extend `TestCase`; mirror source structure
- **Integration tests** (`tests/Integrational/`) extend `KernelTestCase`; use `Tests\Integrational\TestKernel` (minimal kernel with FrameworkBundle + ChamberOrchestraViewBundle)
- Tests call `BindView::setBindUtils(null)`, `SecurityBridge::setTokenStorage(null)` and `LocalisationBridge::setRequestStack(null)` in setUp/tearDown to reset the static bridges; `ReflectionService` uses instance storage (no static reset needed)
- Use data providers for mapping scenarios and cache behavior
- Write code that is easy to test.
- Avoid hard dependencies; use dependency injection where appropriate.
- Do not hardcode time, randomness, UUIDs, or global state.

## Commit Style

Short, action-oriented messages with optional bracketed scope: `[fix] ensure nulls are stripped`, `[master] bump version`. Keep commits focused; avoid unrelated formatting churn.

## General Coding Principles

- Write production-quality code, not illustrative examples.
- Prefer simple, readable solutions over clever ones.
- Avoid premature optimization.
- Do not introduce architectural complexity without clear justification.
- Follow Symfony bundle and directory conventions.
- Use Dependency Injection; never fetch services from the container.
- Do not use static service locators.
- Prefer configuration via services.yaml over hardcoding.
- Use autowiring and autoconfiguration where possible.
- Follow PSR-12 coding standards.
- Use strict types.
- Prefer typed properties and return types everywhere.
- Avoid magic methods unless explicitly required.
- Do not rely on global state or superglobals.

## Structure and Architecture

- Separate business logic, infrastructure, and presentation layers.
- Do not mix side effects with pure logic.
- Minimize coupling between modules.
- Prefer composition to inheritance.
- Services must be small and focused.
- One class — one responsibility.
- Constructor injection only.
- Do not inject the container itself.
- Prefer interfaces for public-facing services.


## Error Handling and Edge Cases

- Handle errors explicitly.
- Never silently swallow exceptions.
- Validate all inputs.
- Consider edge cases and empty or null values.
- Use domain-specific exceptions.
- Do not catch exceptions unless you can handle them meaningfully.
- Fail fast on invalid state.
- Write code that is unit-testable by default.
- Avoid hard dependencies on time, randomness, or static state.
- Use interfaces or abstractions for external services.

## Performance and Resources

- Avoid unnecessary allocations and calls.
- Prevent N+1 queries.
- Assume the code may run on large datasets.

## Documentation and Comments

- Do not comment obvious code.
- Explain *why*, not *what*.
- Add comments when logic is non-trivial.
- Document public services and extension points.
- Comment non-obvious decisions, not implementation details.

## Working with Existing Code

- Preserve the existing codebase style and conventions.
- Do not refactor unrelated code.
- Make the smallest change necessary.

## Assistant Behavior

- Ask clarifying questions if requirements are ambiguous.
- If multiple solutions exist, choose the best one and briefly justify it.
- Avoid deprecated or experimental APIs unless explicitly requested.

## Backward Compatibility

- Do not introduce BC breaks without explicit instruction.
- Follow Symfony bundle versioning and deprecation practices.




