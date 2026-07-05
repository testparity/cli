# S006 Functional Requirements

### Config File Discovery

S006-FR-001 [P1] The configuration system **MUST** discover the `parity.yaml` file using the following resolution order:
S006-FR-001.a When `--config=<path>` is provided, the system **MUST** use that path directly (resolved via `realpath()`).
S006-FR-001.b When `--config` is not provided, the system **MUST** look for `parity.yaml` in the current working directory (`getcwd()`).
S006-FR-001.c The config file **MUST** be named `parity.yaml` (not `.yml`, not `.json`, not `.php`).
S006-FR-001.d If the config file does not exist at the resolved location, the system **MUST** report an error and halt (see S001-EC-001).

### YAML Parsing

S006-FR-002 [P1] The configuration system **MUST** parse the `parity.yaml` file using the Symfony Yaml component (`Symfony\Component\Yaml\Yaml::parse()`).
S006-FR-002.a If the `Yaml` class is not available (`class_exists()` returns false), the system **MUST** print the error: `Install symfony/yaml to use parity.yaml, or use a PHP config.` and return failure.
S006-FR-002.b If the YAML content parses to a non-array value (e.g., a scalar or null), the system **MUST** treat the config as invalid and return null from `loadConfig()`.
S006-FR-002.c If the file cannot be read (`file_get_contents()` returns false), the system **MUST** print: `Could not read config: {path}` and return failure.

### Settings DTO Construction

S006-FR-003 [P1] The `Settings` class **MUST** provide a static factory method `fromConfig(array $config)` that constructs a `Settings` instance from the parsed YAML array.
S006-FR-003.a The factory **MUST** extract configuration from the `settings` sub-key of the config array.
S006-FR-003.b If the `settings` key is absent, the factory **MUST** use an empty array and apply all default values.
S006-FR-003.c All properties of the `Settings` object **MUST** be `public readonly` (immutable after construction).

### Namespace Roots Configuration

S006-FR-004 [P1] The `namespace_roots` setting **MUST** define a mapping from directory prefixes to namespace prefixes (PSR-4 style).
S006-FR-004.a The value **MUST** be an associative array where keys are directory paths (relative to project root) and values are namespace prefixes.
S006-FR-004.b The default value **MUST** be `{'app': 'App', 'tests': 'Tests'}`.
S006-FR-004.c The namespace roots **MUST** be passed to `NamespaceHelper` for path-to-FQCN conversion (see S007).

### File Extension and Test Naming Configuration

S006-FR-005 [P1] The settings system **MUST** support configuring file extensions and test naming conventions through the following keys:
S006-FR-005.a `source_extension` **MUST** define the file extension for source files. Default: `".php"`.
S006-FR-005.b `test_suffix` **MUST** define the suffix appended to the source file basename to form the test file name. Default: `"Test"`.
S006-FR-005.c `test_extension` **MUST** define the file extension for test files. Default: the value of `source_extension` (i.e., if `source_extension` is `".php"` and `test_extension` is not set, `test_extension` defaults to `".php"`).
S006-FR-005.d `namespace_separator` **MUST** define the separator character used in fully qualified class names. Default: `"\\"` (PHP backslash).

### Coverage Path Resolution

S006-FR-006 [P1] The `coverage_xml` config key **MUST** specify where to find coverage data files.
S006-FR-006.a When `coverage_xml` is a string, the system **MUST** wrap it in a single-element array.
S006-FR-006.b When `coverage_xml` is an array, the system **MUST** use the array as-is.
S006-FR-006.c When `coverage_xml` is absent, the system **MUST** default to `['coverage-xml', 'clover.xml', 'cobertura.xml']`.
S006-FR-006.d The resolved array **MUST** be stored in `Settings::$coveragePaths` and consumed by the coverage loading system (S003) which tries candidates in order.
S006-FR-006.e Each candidate path **MUST** be relative to the project root.

### Global Coverage Thresholds

S006-FR-007 [P1] The configuration **MUST** support three global coverage threshold keys at the top level of the config (not inside `settings`):
S006-FR-007.a `min_coverage` **MUST** set the default per-file minimum coverage percentage. Default: `80`. Type: float (cast from config value).
S006-FR-007.b `min_coverage_global` **MUST** set the overall project minimum coverage percentage. Default: null (not enforced). Type: nullable float.
S006-FR-007.c `min_matched_coverage` **MUST** set the default per-file minimum matched coverage percentage (coverage from the matching test file only). Default: null (not enforced). Type: nullable float.
S006-FR-007.d All three thresholds **MUST** be read from the top-level config array, not from the `settings` sub-key.

### Structure Block Format (Modern)

S006-FR-008 [P1] Each entry in the `structure` array **MUST** support the modern format with the following keys:
S006-FR-008.a `name` (string, optional) -- human-readable label for the structure. Default: `"Unnamed"`.
S006-FR-008.b `paths.source` (string, required) -- source directory path relative to project root.
S006-FR-008.c `paths.test` (string, required) -- test directory path relative to project root.
S006-FR-008.d `rules` (array, optional) -- list of rules to evaluate for files in this structure (see S006-FR-009).
S006-FR-008.e `file_map` (associative array, optional) -- explicit source-to-test file overrides (see S006-FR-010).

### Structure Rules Configuration

S006-FR-009 [P1] The `rules` array within a structure block **MUST** support two element formats:
S006-FR-009.a **String format**: A plain string like `"test-exists"` or `"enforce-coverage-link"` that identifies a rule with no parameters.
S006-FR-009.b **Map format**: An associative array like `{"minimum-coverage": {"min": 80}}` where the key is the rule name and the value is an associative array of parameters.
S006-FR-009.c **Named format**: An associative array with a `name` key like `{"name": "minimum-coverage", "min": 80}` where `name` identifies the rule and remaining keys are parameters.
S006-FR-009.d The rules array **MUST** be passed to `RuleRegistry::resolve()` for validation and instantiation (see S002-FR-005).

### File Map Overrides

S006-FR-010 [P2] The `file_map` key within a structure block **MUST** provide explicit source-to-test file mappings that bypass the standard naming convention.
S006-FR-010.a Keys in `file_map` **MUST** be relative paths within the source directory.
S006-FR-010.b Values in `file_map` **MUST** be relative paths within the test directory.
S006-FR-010.c When a source file's relative path (within the source directory) matches a `file_map` key, the mapped value **MUST** be used as the test file path instead of the auto-generated path.
S006-FR-010.d If `file_map` is not an array or is absent, it **MUST** be treated as an empty map.

### Legacy Structure Format Support

S006-FR-011 [P2] The system **MUST** support the legacy structure entry format for backwards compatibility:
S006-FR-011.a `source_path` (string) **MUST** be accepted as an alternative to `paths.source`.
S006-FR-011.b `test_path` (string) **MUST** be accepted as an alternative to `paths.test`.
S006-FR-011.c The `resolvePath()` method **MUST** check for `paths.{key}` first, then fall back to `{key}_path`.
S006-FR-011.d When both `paths.source` and `source_path` are present, `paths.source` **MUST** take precedence.

### Legacy Rule Translation

S006-FR-012 [P2] When a structure entry has no `rules` array, the system **MUST** translate legacy config keys into the equivalent modern rules:
S006-FR-012.a The system **MUST** always start with `['test-exists']` as the base.
S006-FR-012.b If `enforce_coverage_link` is `true` or `"true"`, the system **MUST** add `enforce-coverage-link` to the rules. If `linkers` is also set as an array, it **MUST** be passed as a parameter. If `enforce_attribute` is a string, it **MUST** be passed as the `attribute` parameter.
S006-FR-012.c If `enforce_coverage_link` is not set but `enforce_attribute` is a string, the system **MUST** add `enforce-coverage-link` with `{"attribute": "<value>"}` as parameters.
S006-FR-012.d If neither `enforce_coverage_link` nor `enforce_attribute` is set, the system **MUST** still add `enforce-coverage-link` with no parameters (default linker detection).
S006-FR-012.e The system **MUST** add `minimum-coverage` with the per-structure `min_coverage` value (falling back to `Settings::$minCoverage`) as the `min` parameter.
S006-FR-012.f When PHPUnit XML coverage is in use, the system **MUST** auto-append `matched-coverage` (with optional `min` parameter from `min_matched_coverage`) and `coverage-attribution`.

### Default Values for All Optional Keys

S006-FR-013 [P1] The system **MUST** apply the following defaults when keys are absent from the config:

| Config Key | Location | Default Value |
|------------|----------|---------------|
| `settings` | top-level | `{}` (empty object) |
| `settings.namespace_roots` | settings block | `{'app': 'App', 'tests': 'Tests'}` |
| `settings.source_extension` | settings block | `".php"` |
| `settings.test_suffix` | settings block | `"Test"` |
| `settings.test_extension` | settings block | value of `source_extension` |
| `settings.namespace_separator` | settings block | `"\\"` |
| `coverage_xml` | top-level | `['coverage-xml', 'clover.xml', 'cobertura.xml']` |
| `min_coverage` | top-level | `80` |
| `min_coverage_global` | top-level | `null` (not enforced) |
| `min_matched_coverage` | top-level | `null` (not enforced) |
| `structure` | top-level | `[]` (empty array) |
| `structure[].name` | structure entry | `"Unnamed"` |
| `structure[].rules` | structure entry | auto-generated from legacy keys or defaults |
| `structure[].file_map` | structure entry | `{}` (empty map) |

### Init Command Default Template

S006-FR-014 [P1] The `parity init` command **MUST** generate a `parity.yaml` file containing a complete, well-commented default template.
S006-FR-014.a The template **MUST** include a `settings` block with `namespace_roots`, `source_extension`, `test_suffix`, `test_extension`, and `namespace_separator`.
S006-FR-014.b The template **MUST** include a `coverage_xml` key with default candidates.
S006-FR-014.c The template **MUST** include a `min_coverage` key with value `80`.
S006-FR-014.d The template **MUST** include at least one example `structure` entry using the modern format (with `paths` and `rules`).
S006-FR-014.e The template **MUST** include YAML comments explaining each section.
S006-FR-014.f The template content **MUST** be valid YAML that `Settings::fromConfig()` can parse without error.

### Config Path Override

S006-FR-015 [P1] The `--config=<path>` CLI flag **MUST** override the default config file location.
S006-FR-015.a The path **MUST** be resolved via `realpath()` before use.
S006-FR-015.b If `realpath()` returns false or the resolved path is not a file, the system **MUST** treat the config as not found.
S006-FR-015.c All relative paths within the config file (source paths, test paths, coverage paths) **MUST** be resolved relative to the directory containing the config file, not the current working directory.

### Project Root Derivation

S006-FR-016 [P1] The project root **MUST** be derived from the config file location:
S006-FR-016.a When `--config` is provided, the project root **MUST** be `dirname(realpath(configPath))`.
S006-FR-016.b When `--config` is not provided, the project root **MUST** be `realpath(getcwd())` (provided `parity.yaml` exists there).
S006-FR-016.c All relative paths in the config (structure source/test paths, coverage paths) **MUST** be resolved relative to the project root.

### test-exists Auto-Prepend

S006-FR-017 [P1] When resolving rules for a structure entry using the modern `rules` array format, the system **MUST** automatically prepend the `test-exists` rule if it is not already present in the array.
S006-FR-017.a The check for `test-exists` presence **MUST** inspect both string entries and map entries (checking the key or `name` field).
S006-FR-017.b If `test-exists` is already present in the rules array, it **MUST NOT** be added again.

### PHPUnit XML Rule Auto-Append

S006-FR-018 [P2] When PHPUnit XML coverage data is detected and the modern `rules` array is used, the system **SHOULD** auto-append `matched-coverage` and `coverage-attribution` rules if they are not already listed.
S006-FR-018.a The auto-append **MUST** only occur when `isPhpUnitXml` is true (directory coverage source with `index.xml`).
S006-FR-018.b Each rule **MUST** only be appended if not already present in the rules array.

### Structure Block Iteration Order

S006-FR-019 [P1] The system **MUST** iterate over structure entries in the order they appear in the `structure` array of the config file.
S006-FR-019.a Each structure **MUST** be evaluated independently; failures in one structure **MUST NOT** prevent evaluation of subsequent structures.

### Namespace Separator Configuration

S006-FR-020 [P2] The `namespace_separator` setting **SHOULD** allow Parity to support non-PHP languages:
S006-FR-020.a `\\` for PHP (default).
S006-FR-020.b `.` for Java or Python.
S006-FR-020.c `/` for Go.
S006-FR-020.d The separator **MUST** be used by `NamespaceHelper` when constructing fully qualified class names from file paths.
