# Parity

[![CI](https://github.com/testparity/cli/actions/workflows/ci.yml/badge.svg)](https://github.com/testparity/cli/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/testparity/parity/v/stable)](https://packagist.org/packages/testparity/parity)
[![Total Downloads](https://poser.pugx.org/testparity/parity/downloads)](https://packagist.org/packages/testparity/parity)
[![License](https://poser.pugx.org/testparity/parity/license)](https://packagist.org/packages/testparity/parity)

Structural parity and code coverage validation for any project. Parity ensures application files have belonging tests, verifies coverage ownership, and catches files whose direct tests are weaker than global coverage suggests.

Global coverage answers "did the test suite touch enough code?" Parity answers "did the test that owns this file cover enough of this file?"

## Install

```bash
composer global require testparity/parity
```

Make sure Composer's global bin directory is in your `PATH`:

```bash
# Add to your shell profile (~/.zshrc, ~/.bashrc, etc.)
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

## Quick Start

```bash
# In your project root
parity init    # Creates parity.yaml
parity check   # Run validation

# Options
parity check --format=json          # JSON output for CI
parity check --config=path/to.yaml  # Custom config path
```

Parity reads existing coverage reports; it does not run your test suite. Prefer detailed coverage formats with per-line or per-test attribution when your ecosystem supports them.

```yaml
coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
```

`coverage_xml` can be a single path or an ordered list. Parity uses the first existing path, so teams can prefer attribution-rich reports and fall back to portable reports in CI:

| Format | Best for | Matched coverage |
| --- | --- | --- |
| Parity JSON | Language-neutral per-test attribution from custom converters | Yes |
| PHPUnit XML directory | PHP, PHPUnit, Pest, and line-level test attribution | Yes |
| Clover XML | PHP, JavaScript, TypeScript, and common coverage tools | No |
| Cobertura XML | Rust, Python, Go, JavaScript, TypeScript, JVM, and CI coverage tools | No |

## Development

```bash
composer install
./vendor/bin/pint --test
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never
php parity check --format=json
composer validate --strict
```

This repository dogfoods Parity through the root `parity.yaml`. The self-check expects PHPUnit XML coverage in `coverage-xml/`, so generate coverage before running `php parity check`.

Build the PHAR manually with:

```bash
./vendor/bin/box compile --no-interaction
php parity.phar --version
```

## Samples

Public sample repositories demonstrate Parity across PHP, Laravel, TypeScript, AdonisJS, Rust, PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo. Each sample installs the public Packagist package in GitHub Actions, runs `parity check`, and documents the same proof shape: global coverage can be 80% while the owning test only covers 40% of one file.

| Sample | Language/framework | Test runner | Repository |
|--------|--------------------|-------------|------------|
| PHP | Plain PHP | Fixture coverage | https://github.com/testparity/php-sample |
| Laravel | Laravel-style PHP | Fixture coverage | https://github.com/testparity/laravel-sample |
| TypeScript | TypeScript utility | Fixture coverage | https://github.com/testparity/typescript-sample |
| AdonisJS | AdonisJS-style TypeScript | Fixture coverage | https://github.com/testparity/adonisjs-sample |
| Rust | Plain Rust | Fixture coverage | https://github.com/testparity/rust-sample |
| Cargo | Cargo project | Cargo | https://github.com/testparity/cargo-sample |
| PHPUnit | PHP | PHPUnit | https://github.com/testparity/phpunit-sample |
| Pest | PHP | Pest | https://github.com/testparity/pest-sample |
| Jest | JavaScript | Jest | https://github.com/testparity/jest-sample |
| Mocha | JavaScript | Mocha + NYC | https://github.com/testparity/mocha-sample |
| Vitest | TypeScript | Vitest | https://github.com/testparity/vitest-sample |

See `docs/SAMPLES.md`, `docs/WHY-GLOBAL-COVERAGE-LIES.md`, and `docs/PARITY-COVERAGE-JSON.md`.

The local `samples/` directory contains the original fixtures. Run them from this package root:

```bash
php parity check --config=samples/php/parity.yaml
php parity check --config=samples/laravel/parity.yaml
php parity check --config=samples/vite/parity.yaml
php parity check --config=samples/adonisjs/parity.yaml
php parity check --config=samples/rust/parity.yaml
php parity check --config=samples/phpunit/parity.yaml
php parity check --config=samples/pest/parity.yaml
php parity check --config=samples/jest/parity.yaml
php parity check --config=samples/mocha/parity.yaml
php parity check --config=samples/vitest/parity.yaml
php parity check --config=samples/cargo/parity.yaml
```

## Specs and Docs

Public specs are indexed in `specs/README.md`. Feature docs live in `docs/`, with the complete code/config/plugin/output reference in `docs/REFERENCE.md`. The VitePress website lives in `../parity-website` during this workspace phase and mirrors the full specs tree under `/specs/`.

## Configuration

All behavior is driven by `parity.yaml`. Parity is designed to be framework and language agnostic — settings control how files are discovered, named, and validated.

### Settings

```yaml
settings:
  # Namespace roots: directory prefix -> namespace prefix
  namespace_roots:
    app: App
    tests: Tests
  # File discovery
  source_extension: ".php"       # .ts, .py, .go, etc.
  test_suffix: "Test"            # Spec, _test, .test, etc.
  test_extension: ".php"         # Defaults to source_extension
  # Identifier format
  namespace_separator: "\\"      # . for Java/Python, / for Go
```

### Coverage

```yaml
# Coverage file(s): first existing path is used
# Supports Parity JSON, PHPUnit XML, Clover XML, and Cobertura XML
coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]

# Global thresholds
min_coverage: 80
min_coverage_global: 80
```

### Structure with Rules

Each structure maps a source directory to a test directory and applies rules:

```yaml
structure:
  - name: "Unit Services"
    paths:
      source: "app/Services"
      test: "tests/Unit/Services"
    rules:
      - enforce-coverage-link
      - minimum-coverage:
          min: 80
      - matched-coverage:
          min: 60
    file_map:  # Override test path for specific files
      "Auth/LoginService.php": "Auth/LoginServiceTest.php"
```

## Built-in Rules

Rules are pluggable and registered in the container as `parity.rules.{name}`.

| Rule | Column | Parameters | Description |
|------|--------|------------|-------------|
| `test-exists` | `∃` | none | Test file exists (auto-added) |
| `enforce-coverage-link` | `Link` | `linkers`, `attribute` | Test declares `->covers()` (Pest) or `#[CoversClass]` (PHPUnit) |
| `minimum-coverage` | `Cov` | `min` (required) | Per-file coverage meets threshold |
| `matched-coverage` | `Match` | `min` (optional) | Coverage from matching test file only |
| `coverage-attribution` | `#` + `Other` | none | Shows test count and non-matching test count |

### Rule Parameters

Each rule declares a `parameters()` method that returns validation rules:

```php
// MinimumCoverageRule
public function parameters(): array
{
    return ['min' => 'required|numeric|min:0|max:100'];
}
```

## Plugins

Parity discovers custom rules from three locations:

| Source | Path | Format |
|--------|------|--------|
| Project-local | `.parity/plugins/*.php` | PHP file returning `RuleInterface` |
| Global user | `~/.parity/plugins/*.php` | PHP file returning `RuleInterface` |
| Composer package | `extra.parity.rules` in composer.json | Array of class FQCNs |

### File Plugin

Create a PHP file that returns a `RuleInterface` (or array of them):

```php
// .parity/plugins/naming-convention.php
return new class implements \App\Rules\RuleInterface {
    public function name(): string { return 'naming-convention'; }
    public function parameters(): array { return ['pattern' => 'sometimes|string']; }
    public function evaluate(\App\Rules\RuleContext $context, array $params): \App\Rules\RuleResult { ... }
    public function columnHeader(): ?string { return 'Name'; }
    public function formatCell(\App\Rules\RuleResult $result): string { ... }
    public function isEnforced(): bool { return true; }
};
```

### Composer Plugin Package

Publish a composer package with rules declared in `composer.json`:

```json
{
    "name": "acme/parity-rules",
    "extra": {
        "parity": {
            "rules": [
                "Acme\\Parity\\NamingConventionRule"
            ]
        }
    }
}
```

Then use any plugin rule in `parity.yaml`:

```yaml
rules:
  - naming-convention:
      pattern: "{ClassName}Test.php"
```

## Coverage Linkers

Coverage link detection supports multiple strategies via `CoverageLinkerInterface`:

| Linker | Detects | Example |
|--------|---------|---------|
| `pest-covers` | Pest method chains | `->covers(Foo::class)` |
| `php-attribute` | PHP 8 attributes | `#[CoversClass(Foo::class)]` |

Linkers auto-detect based on file content (Pest vs PHPUnit). Override with:

```yaml
rules:
  - enforce-coverage-link:
      linkers: [pest-covers]  # Only check Pest-style
```

## Legacy Config

The old format (`source_path`, `test_path`, `enforce_attribute`, `min_coverage`) is still fully supported:

```yaml
structure:
  - name: "Unit Actions"
    source_path: "app/Actions"
    test_path: "tests/Unit/Actions"
    enforce_attribute: 'PHPUnit\Framework\Attributes\CoversClass'
    min_coverage: 90
```

## Architecture

```
parity.yaml (config)
    |
    v
Settings (resolved config DTO)
    |
    v
CheckCommand (orchestrator)
    |
    +-- RuleRegistry (resolves rules from config)
    |       |
    |       +-- TestExistsRule
    |       +-- EnforceCoverageLinkRule --> CoverageLinkerRegistry
    |       +-- MinimumCoverageRule
    |       +-- MatchedCoverageRule
    |       +-- CoverageAttributionRule
    |
    +-- CoverageReader / PhpUnitXmlCoverageReader
    +-- NamespaceHelper (configurable path<->FQCN)
    +-- ParityChecker (file-level validation)
```

## License

MIT
