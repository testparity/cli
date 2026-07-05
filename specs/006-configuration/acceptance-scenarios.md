# S006 Acceptance Scenarios

### S006-AS-001 Minimal valid config [P1]

**Given** a `parity.yaml` containing only:
```yaml
coverage_xml: clover.xml
structure:
  - name: "Unit"
    paths:
      source: "app"
      test: "tests/Unit"
```
**When** the config is parsed and `Settings::fromConfig()` is called
**Then** a valid `Settings` object is returned with all defaults applied: `namespaceRoots` = `{'app': 'App', 'tests': 'Tests'}`, `sourceExtension` = `".php"`, `testSuffix` = `"Test"`, `testExtension` = `".php"`, `namespaceSeparator` = `"\\"`, `minCoverage` = `80.0`, `minCoverageGlobal` = `null`, `minMatchedCoverage` = `null`, `coveragePaths` = `['coverage-xml', 'clover.xml', 'cobertura.xml']`.

### S006-AS-002 Full config with all keys specified [P1]

**Given** a `parity.yaml` with every possible key explicitly set:
```yaml
settings:
  namespace_roots:
    src: MyApp
    tests: MyTests
  source_extension: ".ts"
  test_suffix: ".test"
  test_extension: ".ts"
  namespace_separator: "."
coverage_xml: [coverage-xml, clover.xml, custom.xml]
min_coverage: 90
min_coverage_global: 85
min_matched_coverage: 70
structure:
  - name: "Services"
    paths:
      source: "src/services"
      test: "tests/services"
    rules:
      - minimum-coverage:
          min: 95
    file_map:
      "auth.ts": "auth.test.ts"
```
**When** the config is parsed and `Settings::fromConfig()` is called
**Then** every `Settings` property reflects the explicitly set values: `namespaceRoots` = `{'src': 'MyApp', 'tests': 'MyTests'}`, `sourceExtension` = `".ts"`, `testSuffix` = `".test"`, `testExtension` = `".ts"`, `namespaceSeparator` = `"."`, `minCoverage` = `90.0`, `minCoverageGlobal` = `85.0`, `minMatchedCoverage` = `70.0`, `coveragePaths` = `['coverage-xml', 'clover.xml', 'custom.xml']`.

### S006-AS-003 Settings DTO defaults applied when settings block is absent [P1]

**Given** a `parity.yaml` containing:
```yaml
coverage_xml: clover.xml
min_coverage: 75
structure: []
```
(No `settings` key at all)
**When** `Settings::fromConfig()` is called with the parsed array
**Then** `namespaceRoots` defaults to `{'app': 'App', 'tests': 'Tests'}`, `sourceExtension` defaults to `".php"`, `testSuffix` defaults to `"Test"`, `testExtension` defaults to `".php"`, `namespaceSeparator` defaults to `"\\"`, `minCoverage` = `75.0` (from top-level), `coveragePaths` = `['clover.xml']`.

### S006-AS-004 Multiple structures with different rules [P1]

**Given** a `parity.yaml` with three structure entries:
```yaml
structure:
  - name: "Actions"
    paths: { source: "app/Actions", test: "tests/Unit/Actions" }
    rules:
      - minimum-coverage: { min: 95 }
      - enforce-coverage-link
  - name: "Services"
    paths: { source: "app/Services", test: "tests/Unit/Services" }
    rules:
      - minimum-coverage: { min: 80 }
  - name: "Controllers"
    paths: { source: "app/Http/Controllers", test: "tests/Feature" }
    rules:
      - test-exists
```
**When** the check command resolves rules for each structure
**Then** "Actions" has `test-exists` (auto-prepended), `minimum-coverage` (min=95), and `enforce-coverage-link`; "Services" has `test-exists` (auto-prepended) and `minimum-coverage` (min=80); "Controllers" has `test-exists` only (already present, not duplicated). Each structure is evaluated independently with its own rule set.

### S006-AS-005 File map overrides standard naming [P2]

**Given** a structure entry:
```yaml
- name: "Helpers"
  paths: { source: "app/Helpers", test: "tests/Unit/Helpers" }
  file_map:
    "helpers.php": "HelpersTest.php"
    "Legacy/utils.php": "Legacy/UtilsTest.php"
```
And the source directory contains `app/Helpers/helpers.php` and `app/Helpers/Legacy/utils.php`
**When** the check command maps source files to test files
**Then** `helpers.php` maps to `tests/Unit/Helpers/HelpersTest.php` (from file_map), `Legacy/utils.php` maps to `tests/Unit/Helpers/Legacy/UtilsTest.php` (from file_map), and any other files in the directory use the standard naming convention.

### S006-AS-006 Legacy format parsed correctly [P2]

**Given** a `parity.yaml` using the legacy structure format:
```yaml
min_coverage: 85
structure:
  - name: "Unit Actions"
    source_path: "app/Actions"
    test_path: "tests/Unit/Actions"
    enforce_attribute: 'PHPUnit\Framework\Attributes\CoversClass'
    min_coverage: 90
```
**When** the check command resolves the structure entry
**Then** the source path resolves to `"app/Actions"`, the test path resolves to `"tests/Unit/Actions"`, and the legacy keys are translated to rules: `['test-exists', {'enforce-coverage-link': {'attribute': 'PHPUnit\\Framework\\Attributes\\CoversClass'}}, {'minimum-coverage': {'min': 90.0}}]`.

### S006-AS-007 Coverage path as a single string [P1]

**Given** a `parity.yaml` containing:
```yaml
coverage_xml: clover.xml
```
**When** `Settings::fromConfig()` is called
**Then** `coveragePaths` is `['clover.xml']` (string wrapped in array).

### S006-AS-008 Coverage path as an array [P1]

**Given** a `parity.yaml` containing:
```yaml
coverage_xml: [coverage-xml, clover.xml, build/coverage.xml]
```
**When** `Settings::fromConfig()` is called
**Then** `coveragePaths` is `['coverage-xml', 'clover.xml', 'build/coverage.xml']` (array used as-is).

### S006-AS-009 Init creates valid parseable config [P1]

**Given** a directory with no `parity.yaml`
**When** the user runs `parity init`
**Then** the created `parity.yaml` is valid YAML, and parsing it with `Yaml::parse()` followed by `Settings::fromConfig()` produces a `Settings` object with: `namespaceRoots` = `{'app': 'App', 'tests': 'Tests'}`, `sourceExtension` = `".php"`, `testSuffix` = `"Test"`, `minCoverage` = `80.0`, and `coveragePaths` = `['coverage-xml', 'clover.xml', 'cobertura.xml']`. The `structure` array contains at least one entry with `name`, `paths.source`, `paths.test`, and `rules`.

### S006-AS-010 Custom config path resolves project root [P1]

**Given** a `parity.yaml` at `/tmp/myproject/config/parity.yaml` with:
```yaml
coverage_xml: clover.xml
structure:
  - name: "Unit"
    paths: { source: "app", test: "tests" }
```
And a coverage file at `/tmp/myproject/config/clover.xml`
**When** the user runs `parity check --config=/tmp/myproject/config/parity.yaml`
**Then** the project root is set to `/tmp/myproject/config/`, all relative paths in the config are resolved from that root, and `clover.xml` is found at `/tmp/myproject/config/clover.xml`.

### S006-AS-011 test-exists auto-prepended to rules [P1]

**Given** a structure entry with:
```yaml
rules:
  - minimum-coverage: { min: 80 }
  - enforce-coverage-link
```
(Note: `test-exists` not listed)
**When** the check command resolves rules for this structure
**Then** the resolved rules list is: `[test-exists, minimum-coverage, enforce-coverage-link]` -- `test-exists` is prepended automatically.

### S006-AS-012 Legacy enforce_coverage_link with linkers translated [P2]

**Given** a structure entry using legacy format:
```yaml
- name: "Legacy Entry"
  source_path: "app/Models"
  test_path: "tests/Unit/Models"
  enforce_coverage_link: true
  linkers: [pest-covers]
```
**When** the check command resolves rules for this structure
**Then** the resolved rules include `enforce-coverage-link` with parameter `linkers: ['pest-covers']`, translated from the legacy keys.
