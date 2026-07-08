# Implementation Reference

Specs: S001, S002, S003, S004, S005, S006, S007, S008, S010

This page maps the public CLI behavior to the implementation surface in `app/`. It is intended as the complete hand-off reference for maintainers, plugin authors, and deployment owners.

## Commands

### `init`

Implementation: `app/Commands/InitCommand.php`

`parity init` creates `parity.yaml` in the current working directory. It does not overwrite an existing file. The generated template includes:

- `settings.namespace_roots`
- `settings.source_extension`
- `settings.test_suffix`
- `settings.test_extension`
- `settings.namespace_separator`
- `coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]`
- `min_coverage`
- optional `test` block for per-test report generation
- one example `structure` block with `paths.source`, `paths.test`, and rules

Exit behavior:

| Condition | Exit |
| --- | --- |
| New config written | `0` |
| Config already exists | `0` |
| Current directory cannot be resolved | `1` |
| Config file cannot be written | `1` |

### `check`

Implementation: `app/Commands/CheckCommand.php`

`parity check` reads a config file and an existing coverage report. It does not run tests.

Parity dogfoods this command through the root `parity.yaml`: generate coverage with
`XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never`,
then run `php parity check --format=json`.

Options:

| Option | Default | Behavior |
| --- | --- | --- |
| `--config=path/to/parity.yaml` | `./parity.yaml` discovered from current/project root | Sets the config path and makes relative paths resolve from that config's directory. |
| `--format=table` | `table` | Renders human-readable structure tables and a summary. |
| `--format=json` | `table` | Emits a single JSON document with no warnings, headings, ANSI tags, or extra prose. |
| `--show-tests` | `false` | Displays individual test names when the selected coverage source includes attribution data. Has no effect for Clover or Cobertura. |

Runtime sequence:

1. Resolve the project root from `--config` or the current directory.
2. Parse YAML with clean error handling for invalid YAML.
3. Build `Settings` from top-level config.
4. Load project, global, and Composer plugins into the rule registry.
5. Resolve the first existing coverage candidate.
6. Read coverage through `ParityPerTestCoverageReader` for directories with `index.json`, `PhpUnitXmlCoverageReader` for directories with `index.xml`, `ParityJsonCoverageReader` for JSON files, and `CoverageReader` for aggregate XML files.
7. Resolve each structure's rules, auto-prepending `test-exists` and auto-adding attribution rules when the selected coverage source exposes per-test line attribution.
8. Map every source file to its expected test file.
9. Evaluate rules and render table or JSON output.
10. Return failure if any enforced rule fails or global coverage is below threshold.

## Configuration

Implementation: `app/Settings/Settings.php`

Top-level keys:

| Key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `settings` | map | `{}` | File naming and namespace/path behavior. |
| `coverage_xml` | string or list | `[parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]` | Coverage candidates, checked in order. Prefer attribution-capable sources first. |
| `min_coverage` | number | `80` | Default per-file minimum for legacy/generated rules. |
| `min_coverage_global` | number or omitted | `null` | Optional project-level coverage threshold. |
| `min_matched_coverage` | number or omitted | `null` | Optional default for matching-test-only coverage. |
| `structure` | list | `[]` | Source/test directory mappings and rules. |

`settings` keys:

| Key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `namespace_roots` | map | `{ app: App, tests: Tests }` | Maps path roots to identifier roots. |
| `source_extension` | string | `.php` | Source file extension discovered in source paths. |
| `test_suffix` | string | `Test` | Suffix appended to source basename. |
| `test_extension` | string | same as `source_extension` | Test file extension. |
| `namespace_separator` | string | `\\` | Identifier separator for generated FQCN-like names. |

Structure formats:

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
    file_map:
      "Auth/LoginService.php": "Auth/LoginServiceTest.php"
```

Legacy structure keys remain supported:

```yaml
structure:
  - name: "Legacy Actions"
    source_path: "app/Actions"
    test_path: "tests/Unit/Actions"
    enforce_attribute: "PHPUnit\\Framework\\Attributes\\CoversClass"
    min_coverage: 90
```

## Path and Namespace Mapping

Implementation: `app/Services/NamespaceHelper.php`

`NamespaceHelper` normalizes path separators, strips configured source extensions, applies namespace roots, and converts source paths to expected test paths.

Examples:

| Settings | Source | Expected Test |
| --- | --- | --- |
| PHP defaults | `app/Services/Invoice.php` | `tests/Unit/Services/InvoiceTest.php` |
| TypeScript sample | `src/formatCurrency.ts` | `tests/formatCurrency.test.ts` |
| Rust sample | `src/lib.rs` | `tests/lib_test.rs` |

`file_map` overrides the automatic mapping for a specific source-relative path.

## Coverage Readers

### Single-file XML reader

Implementation: `app/Services/CoverageReader.php`

Supported file formats:

| Format | Detection | Per-file coverage | Global coverage | Per-test attribution |
| --- | --- | --- | --- | --- |
| Parity per-test directory | Existing configured directory with `index.json` | merged from covered line sets | merged from all source files | Yes |
| Parity JSON | Existing configured `.json` file | from `coveragePercent` or computed from covered lines | from `globalPercent` | Yes |
| Clover XML | Any existing configured file with Clover `<file><metrics>` nodes | `coveredstatements / statements * 100` | project `<metrics files="...">` | No |
| Cobertura XML | Any existing configured file with Cobertura `<class filename line-rate>` nodes | `line-rate * 100`, or line-hit fallback | root `<coverage line-rate>` | No |

Malformed XML, missing files, unreadable files, missing metrics, and empty paths return empty/null results without throwing.

### Attribution-capable readers

Implementation: `app/Services/ParityPerTestCoverageReader.php`, `app/Services/ParityJsonCoverageReader.php`, `app/Services/PhpUnitXmlCoverageReader.php`

If a configured coverage candidate is a directory containing `index.json`, Parity treats it as its native per-test report format. If the candidate is a `.json` file, Parity treats it as `parity-coverage.json`. If the candidate is a directory containing `index.xml`, Parity treats it as PHPUnit XML coverage.

All three attribution-capable formats provide:

- per-file line coverage percentages
- project/global coverage
- per-line executable counts
- test names that covered each line
- `testsByFile` data for attribution and incidental coverage reporting

### PHPUnit XML directory reader

Implementation: `app/Services/PhpUnitXmlCoverageReader.php`

For PHP projects, PHPUnit XML remains the most direct native attribution source.

### `test`

Implementation: `app/Commands/TestCommand.php`

`parity test` runs each expected test file individually, normalizes the resulting single-test coverage artifact into Parity's native per-test report format, writes a report directory, and then runs `parity check` against that directory by default.

Config surface:

```yaml
test:
  command: "./vendor/bin/pest {test_abs} --coverage-clover={coverage}"
  coverage: ".parity/tmp/{slug}.xml"
  reports: ".parity/per-test"
```

Supported placeholders:

- `{test}` relative expected test path
- `{test_abs}` absolute expected test path
- `{coverage}` path where the single-test coverage artifact must be written
- `{slug}` stable short hash derived from the expected test path
- `{project_root}` absolute project root

## Rules

Built-in rules are registered in `app/Providers/AppServiceProvider.php`.

| Rule | Implementation | Enforced | Parameters | Output Column |
| --- | --- | --- | --- | --- |
| `test-exists` | `TestExistsRule` | Yes | none | `∃` |
| `enforce-coverage-link` | `EnforceCoverageLinkRule` | Yes | `linkers`, `attribute` | `Link` |
| `minimum-coverage` | `MinimumCoverageRule` | Yes | required `min` number `0..100` | `Cov` |
| `matched-coverage` | `MatchedCoverageRule` | Yes | optional `min` number `0..100` | `Match` |
| `coverage-attribution` | `CoverageAttributionRule` | No | optional `show_names` | `#`, `Other` |

Rule config formats:

```yaml
rules:
  - test-exists
  - minimum-coverage:
      min: 80
  - name: minimum-coverage
    min: 90
```

Unknown rule names or invalid parameters fail cleanly before evaluation.

## Coverage Linkers

Implementation: `app/Services/CoverageLinkers/`

| Linker | Config Name | Supports | Declarations |
| --- | --- | --- | --- |
| `PestCoversLinker` | `pest-covers` | Pest-style files without class declarations that use `it()`, `test()`, `describe()`, `beforeEach()`, or `beforeAll()` | `->covers(Foo::class)`, `->coversClass(Foo::class)`, `covers(Foo::class)` |
| `PhpAttributeLinker` | `php-attribute` | PHP files with class declarations | `#[CoversClass(Foo::class)]` before the test class |

`CoverageLinkerRegistry::fromConfig()` accepts:

```yaml
rules:
  - enforce-coverage-link:
      linkers: [auto]
      attribute: PHPUnit\Framework\Attributes\CoversClass
```

Valid `linkers` values are `auto`, `pest-covers`, and `php-attribute`. `auto` uses both built-in linkers. `attribute` customizes the PHP attribute class name for the PHP attribute linker.

Class reference resolution supports:

- imported class aliases from `use Foo\Bar as Baz;`
- imported short names from `use Foo\Bar;`
- relative names in the current namespace
- leading-backslash absolute names
- string literal FQCNs

## Plugin System

Implementation: `app/Services/PluginLoader.php`

Load order:

1. project-local `.parity/plugins/*.php`
2. user-global `~/.parity/plugins/*.php`
3. Composer packages declaring `extra.parity.rules`

Project/global plugin files must return either a `RuleInterface` instance or an array of `RuleInterface` instances.

Composer plugin declaration:

```json
{
  "extra": {
    "parity": {
      "rules": [
        "Acme\\Parity\\Rules\\NamingRule"
      ]
    }
  }
}
```

Plugin warnings are printed in table mode with a `Plugin:` prefix. They are suppressed in JSON mode so stdout remains parseable.

Plugins are trusted executable PHP. Do not load unreviewed plugins in public CI.

## Output Contracts

### Table mode

Table mode renders:

1. optional global coverage message
2. one section per structure
3. source/test paths for each structure
4. a dynamic table based on resolved rules
5. a final summary table
6. optional unmatched-test warnings when PHPUnit XML attribution is available

The first two columns are always `Source` and `Test`; the final column is always `OK`. Rule columns are added from `RuleInterface::columnHeader()`.

### JSON mode

JSON mode emits only this top-level shape:

```json
{
  "passed": true,
  "global_coverage": null,
  "min_coverage_global": null,
  "structures": []
}
```

Each structure contains `name`, `source_path`, `test_path`, and `files`. Each file contains `source`, `test`, `passed`, and `rules`. Each rule result contains `passed`, `value`, and `error`.

## Release and Runtime Providers

`config/app.php` reads `APP_VERSION` or `VERSION`, then falls back to `unreleased`.

`config/commands.php` is the Laravel Zero command registration file. Public commands are registered through this runtime configuration and backed by `app/Commands/InitCommand.php` and `app/Commands/CheckCommand.php`.

`app/Providers/GitVersionFallbackProvider.php` binds `git.version` to the same `VERSION` fallback behavior for packaged/runtime contexts.

`dev/release-version.sh` handles dry-run and real version bumps, changelog updates, tag creation, PHAR build guidance, and dirty-worktree protection.

## Samples

The sample matrix validates representative layouts and coverage formats:

| Sample | Language/Framework | Coverage Fixture |
| --- | --- | --- |
| `samples/php` | Plain PHP | Clover |
| `samples/laravel` | Laravel-style PHP | Clover |
| `samples/vite` | Vite/TypeScript | Cobertura |
| `samples/adonisjs` | AdonisJS-style TypeScript | Clover |
| `samples/rust` | Rust-style layout | Clover |
| `samples/phpunit` | Runnable PHPUnit app | Clover |
| `samples/pest` | Runnable Pest app | Clover plus Pest `->covers()` link validation |
| `samples/jest` | Runnable Jest app | Clover |
| `samples/mocha` | Runnable Mocha app | Clover |
| `samples/vitest` | Runnable Vitest app | Clover |
| `samples/cargo` | Runnable Cargo/Rust crate | Cobertura |
