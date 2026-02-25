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

### Request/Response Flow

1. **SetVersionSubscriber** (priority 256, early) — on `RequestEvent`, calls `BindView::setBindUtils()` to inject the DI-managed `BindUtils` instance into the static bridge
2. Controller returns a `ViewInterface` object
3. **ViewSubscriber** — on `ViewEvent`, detects `ViewInterface` results, wraps non-`ResponseViewInterface` in `DataView`, serializes to JSON, and sets status/headers

### Property Binding (BindUtils)

`BindUtils` is a DI service that synchronizes properties between source objects and BindView instances. Configured via constructor (`$buildId`, `$debug`, `$shareDir`) and registered in the container by `services.php`. It uses reflection to find intersecting properties, validates type compatibility, and handles:
- Built-in types, custom objects, ViewInterface subclasses (auto-constructed), IterableView with `#[Type]` attribute
- Skips union types and incompatible types
- Property accessor caching enabled when `$buildId` is non-empty; 24h lifetime in production (`$debug=false`), disabled in debug mode
- Exposes `isReflectionTypeValidForInitialization()`, `isView()`, and `isAutoConfigurableType()` as `public static` utility methods (shared with `BindUtilsCacheWarmer`)

### Doctrine Integration

`ReflectionPropertyAccessor` decorates Symfony's PropertyAccessor and initializes Doctrine proxy objects before accessing their properties.

### Serializer & Metadata

`ViewNormalizer` handles `ViewInterface` instances, strips null values from output, and delegates nested normalization. It uses `ViewMetadataFactory` to introspect view classes.

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
- Tests call `BindView::setBindUtils(null)` in setUp/tearDown to reset the static bridge; `ReflectionService` uses instance storage (no static reset needed)
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




