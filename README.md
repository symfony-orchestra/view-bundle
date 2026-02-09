[![PHP Composer](https://github.com/chamber-orchestra/view-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/chamber-orchestra/view-bundle/actions/workflows/php.yml)

# ChamberOrchestra View Bundle

A lightweight Symfony bundle for building typed, reusable JSON responses. Views encapsulate serialization concerns so controllers can return simple objects instead of `Response`.

## Requirements
- PHP 8.5+
- Symfony 8.0 components (http-kernel, serializer, property-access, dependency-injection, config)
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

## Quickstart
Create a view that maps fields from a domain object:
```php
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\IterableView;

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

Return a view from a controller:
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
`ViewSubscriber` converts any `ViewInterface` result into a `JsonResponse`. Non-view results are ignored.

## Core Views
- `ResponseView`: base response with status (200) and headers (`Content-Type: application/json`), overridable in subclasses.
- `DataView`: wraps payload under `data`.
- `BindView`: maps matching properties from a source object to the view; honors `Attribute\Type` on `IterableView` properties for typed collections.
- `IterableView`: maps collections via a callback or view class string.
- `KeyValueView`: returns an associative array for metadata blocks.

## Performance Optimizations

The bundle includes two-phase optimization for production environments:

**Phase 1: Runtime Metadata Caching**
- `ViewMetadataFactory` caches property metadata in memory
- Direct property access eliminates repeated reflection calls
- 30-50% faster normalization

**Phase 2: Persistent Cache Warming**
- `ViewMetadataCacheWarmer` pre-computes view metadata at build time
- `BindUtilsCacheWarmer` pre-computes View-to-View property mappings
- Generated opcache-optimized PHP files in cache directory
- 60-80% reduction in reflection overhead on production requests
- Automatic cache invalidation via `container.build_id`

**Cache Configuration**
`SetVersionSubscriber` configures `BindUtils` with cache directory and build ID. When `APP_DEBUG=false`, caching is enabled with 24h lifetime and namespace `view_bind`.

**Warm the cache in production:**
```bash
bin/console cache:warmup --env=prod
```

This generates:
- `var/cache/prod/view_metadata.php` - View property metadata
- `var/cache/prod/bind_utils_mappings.php` - Property mappings

## Development & Tests
- Install deps: `composer install`
- Run unit/integration tests: `./bin/phpunit` 
- Namespaces live under `ChamberOrchestra\ViewBundle`; autoloaded PSR-4 from `src/`.

## Performance Benchmarking

Benchmark tools are included to measure optimization impact:

**Quick performance test:**
```bash
php benchmark/simple-timing.php
```

**Cache warmup impact test:**
```bash
php benchmark/cache-warmup-test.php
```

**Memory usage analysis:**
```bash
php benchmark/memory-test.php
```

**Professional benchmarking with PHPBench:**
```bash
composer require --dev phpbench/phpbench
vendor/bin/phpbench run --report=default
```

**Expected Results:**
- Normalization: 2-3x faster with cache warming
- Operations per second: 300,000+ normalizations/sec
- Memory overhead: Minimal (cached metadata)


