# S006 Interface Requirements

### parity.yaml Full Schema

S006-IF-001 [P1] The `parity.yaml` file **MUST** conform to the following complete schema. All keys are optional unless marked otherwise.

```yaml
# ── Global Settings ────────────────────────────────────────────
settings:
  namespace_roots:                   # map<string, string>
    <directory_prefix>: <namespace_prefix>
    # Default: { app: App, tests: Tests }

  source_extension: <string>         # Default: ".php"
  test_suffix: <string>              # Default: "Test"
  test_extension: <string>           # Default: value of source_extension
  namespace_separator: <string>      # Default: "\\"

# ── Coverage Source ────────────────────────────────────────────
coverage_xml: <string | list<string>>
  # Default: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
  # Paths relative to project root
  # First existing file or directory (with index.xml) is used

# ── Global Thresholds ─────────────────────────────────────────
min_coverage: <number>               # Default: 80 (per-file minimum %)
min_coverage_global: <number | null> # Default: null (not enforced)
min_matched_coverage: <number | null> # Default: null (not enforced)

# ── Structure Definitions ─────────────────────────────────────
structure:                           # list<StructureEntry>
  - name: <string>                   # Optional. Default: "Unnamed"
    paths:                           # Required (modern format)
      source: <string>               # Required. Relative to project root.
      test: <string>                 # Required. Relative to project root.
    rules:                           # Optional. list<string | map>
      - <rule-name>                  # String format (no params)
      - <rule-name>:                 # Map format (with params)
          <param>: <value>
    file_map:                        # Optional. map<string, string>
      <source_relative>: <test_relative>
```

S006-IF-001.a Every key in the schema **MUST** have a defined type and default value as documented.
S006-IF-001.b Unknown top-level keys **MUST** be silently ignored (forward compatibility).
S006-IF-001.c Unknown keys within `settings` **MUST** be silently ignored.

### Settings DTO Full Field Reference

S006-IF-002 [P1] The `Settings` class constructor **MUST** accept the following parameters, each mapping to a `public readonly` property:

| Property | Type | Config Source | Default |
|----------|------|---------------|---------|
| `$namespaceRoots` | `array<string, string>` | `settings.namespace_roots` | `['app' => 'App', 'tests' => 'Tests']` |
| `$sourceExtension` | `string` | `settings.source_extension` | `'.php'` |
| `$testSuffix` | `string` | `settings.test_suffix` | `'Test'` |
| `$testExtension` | `string` | `settings.test_extension` | value of `$sourceExtension` |
| `$namespaceSeparator` | `string` | `settings.namespace_separator` | `'\\'` |
| `$minCoverage` | `float` | `min_coverage` (top-level) | `80.0` |
| `$minCoverageGlobal` | `?float` | `min_coverage_global` (top-level) | `null` |
| `$minMatchedCoverage` | `?float` | `min_matched_coverage` (top-level) | `null` |
| `$coveragePaths` | `array<string>` | `coverage_xml` (top-level) | `['coverage-xml', 'clover.xml', 'cobertura.xml']` |

S006-IF-002.a All properties **MUST** be declared as `public readonly`.
S006-IF-002.b The constructor **MUST** accept all properties as named parameters.

### Settings::fromConfig() Contract

S006-IF-003 [P1] The `Settings::fromConfig(array $config): self` method **MUST** implement the following mapping logic:

```php
public static function fromConfig(array $config): self
{
    $settingsBlock = $config['settings'] ?? [];

    return new self(
        namespaceRoots:      $settingsBlock['namespace_roots']
                             ?? ['app' => 'App', 'tests' => 'Tests'],
        sourceExtension:     $settingsBlock['source_extension'] ?? '.php',
        testSuffix:          $settingsBlock['test_suffix'] ?? 'Test',
        testExtension:       $settingsBlock['test_extension']
                             ?? ($settingsBlock['source_extension'] ?? '.php'),
        namespaceSeparator:  $settingsBlock['namespace_separator'] ?? '\\',
        minCoverage:         (float) ($config['min_coverage'] ?? 80),
        minCoverageGlobal:   isset($config['min_coverage_global'])
                             ? (float) $config['min_coverage_global'] : null,
        minMatchedCoverage:  isset($config['min_matched_coverage'])
                             ? (float) $config['min_matched_coverage'] : null,
        coveragePaths:       self::resolveCoveragePaths($config),
    );
}
```

S006-IF-003.a The `min_coverage` value **MUST** be cast to `float` via `(float)`.
S006-IF-003.b The `min_coverage_global` and `min_matched_coverage` values **MUST** remain `null` when their keys are absent from the config (using `isset()` check, not null coalescing).
S006-IF-003.c The `test_extension` default **MUST** fall through to the `source_extension` value (or its default `'.php'`), not independently default to `'.php'`.

### Settings::resolveCoveragePaths() Contract

S006-IF-003.d The private `resolveCoveragePaths(array $config): array` method **MUST** implement:

```php
private static function resolveCoveragePaths(array $config): array
{
    $coverageXml = $config['coverage_xml'] ?? ['coverage-xml', 'clover.xml', 'cobertura.xml'];
    return is_array($coverageXml) ? $coverageXml : [$coverageXml];
}
```

S006-IF-003.e When `coverage_xml` is a string, it **MUST** be wrapped in a single-element array.
S006-IF-003.f When `coverage_xml` is already an array, it **MUST** be returned as-is.

### Structure Block Schema (Modern)

S006-IF-004 [P1] A modern structure entry **MUST** have the following shape:

```yaml
- name: "Unit Services"        # string, optional
  paths:                        # object, required for modern format
    source: "app/Services"      # string, required
    test: "tests/Unit/Services" # string, required
  rules:                        # list, optional
    - test-exists
    - enforce-coverage-link:
        linkers: [pest-covers, php-attribute]
    - minimum-coverage:
        min: 90
  file_map:                     # map, optional
    "Auth/LoginService.php": "Auth/LoginServiceTest.php"
```

S006-IF-004.a The `paths` object **MUST** contain both `source` and `test` as strings.
S006-IF-004.b Paths **MUST** be relative to the project root, without leading or trailing slashes.
S006-IF-004.c The `rules` array **MUST** follow the formats defined in S006-IF-006.
S006-IF-004.d The `file_map` keys and values **MUST** be relative to their respective `paths.source` and `paths.test` directories.

### Structure Block Schema (Legacy)

S006-IF-005 [P2] A legacy structure entry **MUST** be recognized by the following shape:

```yaml
- name: "Unit Actions"
  source_path: "app/Actions"          # string (legacy equivalent of paths.source)
  test_path: "tests/Unit/Actions"     # string (legacy equivalent of paths.test)
  enforce_attribute: 'PHPUnit\Framework\Attributes\CoversClass'  # string, optional
  enforce_coverage_link: true         # boolean or string "true", optional
  linkers: [pest-covers]             # array of strings, optional
  min_coverage: 90                   # number, optional (per-structure override)
  min_matched_coverage: 80           # number, optional (per-structure override)
```

S006-IF-005.a Legacy entries are identified by the absence of both `paths` and `rules` keys, with presence of `source_path` or `test_path`.
S006-IF-005.b The `enforce_attribute` value **MUST** be a fully qualified PHP class name string (e.g., `PHPUnit\Framework\Attributes\CoversClass`).
S006-IF-005.c The `enforce_coverage_link` value **MUST** be accepted as boolean `true`, string `"true"`, or absent.

### Rule Configuration Formats

S006-IF-006 [P1] Rules within the `rules` array **MUST** support three syntactic forms:

**Form 1 -- String (no parameters):**
```yaml
rules:
  - test-exists
  - enforce-coverage-link
```

**Form 2 -- Map (key is rule name, value is parameters):**
```yaml
rules:
  - minimum-coverage:
      min: 80
  - enforce-coverage-link:
      linkers: [pest-covers]
      attribute: "PHPUnit\\Framework\\Attributes\\CoversClass"
```

**Form 3 -- Named map (explicit `name` key):**
```yaml
rules:
  - name: minimum-coverage
    min: 80
```

S006-IF-006.a For Form 2, the system **MUST** extract the rule name as `array_key_first($config)` and parameters as the value.
S006-IF-006.b For Form 3, the system **MUST** extract the rule name from the `name` key and parameters as all remaining keys.
S006-IF-006.c All three forms **MUST** produce identical resolved output when given the same rule name and parameters.

### Built-in Rule Parameter Schemas

S006-IF-006.d The following built-in rules accept these parameters when configured in `rules`:

| Rule Name | Parameter | Type | Required | Default | Description |
|-----------|-----------|------|----------|---------|-------------|
| `test-exists` | (none) | -- | -- | -- | No parameters |
| `enforce-coverage-link` | `linkers` | `array<string>` | No | all registered | Linker names to try |
| `enforce-coverage-link` | `attribute` | `string` | No | `CoversClass` | PHP attribute FQCN |
| `minimum-coverage` | `min` | `numeric` | No | `Settings::$minCoverage` | Minimum coverage % |
| `matched-coverage` | `min` | `numeric` | No | `Settings::$minMatchedCoverage` | Minimum matched coverage % |
| `coverage-attribution` | (none) | -- | -- | -- | No parameters (informational) |

### Default Template Content

S006-IF-007 [P1] The `parity init` default template **MUST** produce the following structure (comments may vary, but all config keys and values must match):

```yaml
settings:
  namespace_roots:
    app: App
    tests: Tests
  source_extension: ".php"
  test_suffix: "Test"
  test_extension: ".php"
  namespace_separator: "\\"

coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]

min_coverage: 80

structure:
  - name: "Unit Actions"
    paths:
      source: "app/Actions"
      test: "tests/Unit/Actions"
    rules:
      - enforce-coverage-link
      - minimum-coverage:
          min: 90
```

S006-IF-007.a The template **MUST** use the modern format (`paths` + `rules`), not the legacy format.
S006-IF-007.b The template **MUST** be valid YAML parseable by `Yaml::parse()`.
S006-IF-007.c The template **MUST** include explanatory YAML comments for each section.
S006-IF-007.d Parsing the template with `Settings::fromConfig()` **MUST** produce a valid `Settings` object without errors.
