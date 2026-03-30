# Parity

Structural parity and code coverage validation for any project. Ensures test files exist, declare what they cover, and meet coverage thresholds — without running tests.

## Install

```bash
composer global require ulties/parity
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
# Supports Clover XML (single file) or PHPUnit XML (directory with index.xml)
coverage_xml: [coverage-xml, clover.xml]

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
