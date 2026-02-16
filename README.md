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

### BindView Property Binding

`BindView` uses `BindUtils` to synchronize properties between source objects and view instances. It handles:

- Built-in PHP types and custom objects
- `ViewInterface` subclasses (auto-constructed)
- `IterableView` properties with `#[Type(ViewClass::class)]` attribute for typed collections
- Skips union types and incompatible type pairs

## Architecture

### Request/Response Flow

1. **SetVersionSubscriber** (priority 256) — configures `BindUtils` with `kernel.share_dir`, `container.build_id`, and enables property accessor caching when `APP_DEBUG=false`
2. Controller returns a `ViewInterface` object
3. **ViewSubscriber** — detects `ViewInterface` results, wraps non-`ResponseViewInterface` in `DataView`, serializes to JSON via `ViewNormalizer`

### View Auto-Discovery

Views implementing `ViewInterface` are automatically tagged with `chamber_orchestra.view` via `#[AutoconfigureTag]`. The `ViewPass` compiler pass collects these classes and passes them to cache warmers for pre-computation.

## Performance Optimizations

The bundle includes a two-phase optimization strategy for production environments:

### Phase 1: Runtime Metadata Caching

- `ViewMetadataFactory` caches property metadata in memory
- Direct property access eliminates repeated reflection calls
- 30-50% faster normalization on repeated calls

### Phase 2: Build-Time Cache Warming

- `ViewMetadataCacheWarmer` pre-computes view property metadata at build time
- `BindUtilsCacheWarmer` pre-computes view-to-view property mappings
- Generated opcache-optimized PHP files stored in `kernel.share_dir`
- Cache files are versioned with `container.build_id` for safe deployments
- 60-80% reduction in reflection overhead on production requests
- Automatic fallback to reflection when warmed cache is unavailable

### Cache Configuration

`SetVersionSubscriber` configures `BindUtils` with the share directory and build ID. When `APP_DEBUG=false`, property accessor caching is enabled with a 24-hour lifetime.

**Warm the cache in production:**

```bash
bin/console cache:warmup --env=prod
```

This generates build-versioned files in the shared cache directory:
- View property metadata (nullability, defaults, types)
- View-to-view property mappings for `BindUtils`

## Benchmarks

Benchmark scripts are included to measure serialization performance and cache impact:

```bash
# Quick normalization timing
php benchmark/simple-timing.php

# Cache warmup impact test
php benchmark/cache-warmup-test.php

# Memory usage analysis
php benchmark/memory-test.php
```

**PHPBench integration:**

```bash
composer require --dev phpbench/phpbench
vendor/bin/phpbench run --report=default
```

## Development

```bash
composer install          # Install dependencies
composer test             # Run all tests (87 tests, 326 assertions)
./bin/phpunit             # Run tests directly
./bin/phpunit --filter X  # Run specific test class or method
```

## License

MIT
