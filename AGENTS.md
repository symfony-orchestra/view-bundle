# Repository Guidelines

## Project Structure & Module Organization
- Core library code lives in `src/` under `ChamberOrchestra\ViewBundle\*` (notably `View/`, `Attribute/`, `EventSubscriber/`, `Serializer/`).
- Bundle entry point is `src/ChamberOrchestraViewBundle.php`; shared helpers sit in `src/Utils/` and `src/PropertyAccessor/`.
- Tests belong in `tests/` (autoloaded as `Tests\`); tools are in `bin/` (`bin/phpunit`).
- Autoloading is PSR-4; place new modules inside `src/` with matching namespaces.
- Requirements: PHP 8.5+, Symfony 8.0 components, `doctrine/common` ^3.5.

## Build, Test, and Development Commands
- Install dependencies: `composer install` (PHP 8.4+, Symfony 8.0 components).
- Run the suite: `./bin/phpunit` (uses `phpunit.xml.dist`). Add `--filter ClassNameTest` or `--filter testMethodName` to scope.
- `composer test` is an alias for `./bin/phpunit`.
- Quick lint: `php -l path/to/File.php`; keep code PSR-12 even though no fixer is bundled.
- In host apps, return a `ViewInterface` object from controllers instead of `Response`.

## Coding Style & Naming Conventions
- Follow PSR-12: 4-space indent, one class per file, strict types (`declare(strict_types=1);`).
- Use typed properties and return types; favor `readonly` where appropriate.
- Class names in `View` modules end with `View`; attributes live in `Attribute/` with descriptive names; utilities use verbs (e.g., `BindUtils`).
- Keep constructors light; prefer small, composable services injected via Symfony DI.
- JSON structures should be explicit; avoid leaking nulls unless intentional.

## Testing Guidelines
- Use PHPUnit (12.x). Name files `*Test.php` mirroring the class under test (e.g., `View/DataViewTest.php`).
- Unit tests live in `tests/Unit/` (`TestCase`); integration tests in `tests/Integrational/` (`KernelTestCase`) using `Tests\Integrational\TestKernel`.
- Keep tests deterministic; use data providers for mapping scenarios and caches.
- Cover serializer/property accessor mapping, nullable cases, and cache behavior.
- Target high coverage on `View/`, `Attribute/`, and subscriber behaviors; include regression tests when fixing bugs.

## Commit & Pull Request Guidelines
- Commit messages mirror existing history: short, action-oriented, optionally bracketed scope (e.g., `[fix] ensure nulls are stripped`, `[master] bump version`).
- Keep commits focused; avoid unrelated formatting churn.
- Pull requests should include: purpose summary, key changes, test results (`./bin/phpunit` output), and any API/response shape changes. Add screenshots only when JSON shape or DX changes benefit from examples.
- Reference related issues/links; note backward compatibility considerations (new attributes, changed defaults, cache impacts).

## Security & Configuration Tips
- Ensure `APP_DEBUG=false` in production so `PropertyAccessor::createCache` is used; clear caches when updating view schemas.
- Validate any user-derived data before it enters Views; Views should remain serialization-only layers.
- Keep dependencies up to date with `composer update` only when needed and verify against supported Symfony 8.0 constraints.
