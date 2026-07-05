# S007 Interface Requirements

### NamespaceHelper::pathToFqcn

S007-IF-001 [P1] `NamespaceHelper::pathToFqcn()` **MUST** have the following signature:

```php
/**
 * Convert a path relative to project root to a fully qualified class name.
 * e.g. app/Actions/Store.php -> App\Actions\Store
 *      tests/Unit/Actions/StoreTest.php -> Tests\Unit\Actions\StoreTest
 *
 * @param string $relativePath  Path relative to project root (e.g. 'app/Services/Foo.php')
 * @return string Fully qualified class name (e.g. 'App\Services\Foo')
 */
public function pathToFqcn(string $relativePath): string;
```

S007-IF-001.a The method **MUST** accept a single string parameter representing the relative file path.
S007-IF-001.b The method **MUST** return a non-empty string representing the FQCN.
S007-IF-001.c The returned FQCN **MUST NOT** include the file extension.
S007-IF-001.d The returned FQCN **MUST NOT** have a leading backslash.

### NamespaceHelper::sourcePathToTestPath

S007-IF-002 [P1] `NamespaceHelper::sourcePathToTestPath()` **MUST** have the following signature:

```php
/**
 * Convert a source file path to the expected test file path.
 * e.g. app/Actions/User.php -> tests/Unit/Actions/UserTest.php
 *
 * @param string $sourceRelativePath  Full relative path to source file (e.g. 'app/Services/Foo.php')
 * @param string $sourcePathBase      Source directory prefix to strip (e.g. 'app/Services')
 * @param string $testPathBase        Test directory prefix to prepend (e.g. 'tests/Unit/Services')
 * @return string Expected test file path (e.g. 'tests/Unit/Services/FooTest.php')
 */
public function sourcePathToTestPath(
    string $sourceRelativePath,
    string $sourcePathBase,
    string $testPathBase
): string;
```

S007-IF-002.a `$sourceRelativePath` **MUST** be the full path relative to the project root, not relative to the source directory.
S007-IF-002.b `$sourcePathBase` and `$testPathBase` **MUST** be directory paths without trailing slashes (the method normalizes trailing slashes internally).
S007-IF-002.c The returned path **MUST** use forward slashes as directory separators regardless of platform.

### NamespaceHelper::normalizeRelativePath

S007-IF-003 [P1] `NamespaceHelper::normalizeRelativePath()` **MUST** have the following signature:

```php
/**
 * Normalize path for comparison (no double slashes, consistent dir separators).
 *
 * @param string $path  Raw relative path to normalize
 * @return string Normalized path with forward slashes, no leading/trailing slashes, no double slashes
 */
public function normalizeRelativePath(string $path): string;
```

S007-IF-003.a The returned path **MUST** use forward slashes exclusively.
S007-IF-003.b The returned path **MUST NOT** have leading or trailing slashes.
S007-IF-003.c Consecutive slashes **MUST** be collapsed to a single slash.

### NamespaceHelper Constructor

S007-IF-004 [P1] `NamespaceHelper::__construct()` **MUST** accept two optional parameters:

```php
/**
 * @param array<string, string>|null $roots  Directory prefix => namespace prefix mappings
 * @param Settings|null $settings            Full settings object (takes precedence over $roots)
 */
public function __construct(?array $roots = null, ?Settings $settings = null);
```

S007-IF-004.a When `$settings` is provided, all configuration **MUST** be sourced from the Settings object. The `$roots` parameter **MUST** be ignored.
S007-IF-004.b When only `$roots` is provided, defaults **MUST** apply for: `sourceExtension` (`.php`), `testSuffix` (`Test`), `testExtension` (`.php`), `namespaceSeparator` (`\`).
S007-IF-004.c When neither parameter is provided, roots **MUST** default to `['app' => 'App', 'tests' => 'Tests']`.

### Structure Block Config Schema

S007-IF-005 [P1] Each structure block in `parity.yaml` **MUST** conform to the following schema:

```yaml
structure:
  - name: string           # Required. Human-readable label.
    paths:                  # Required (or legacy source_path/test_path).
      source: string       # Required. Source directory relative to project root.
      test: string         # Required. Test directory relative to project root.
    rules: list            # Optional. List of rule names or rule-with-params maps.
    file_map: map          # Optional. Source-relative path => test-relative path overrides.
```

S007-IF-005.a The `paths` block **MUST** have both `source` and `test` keys.
S007-IF-005.b Legacy format (`source_path` and `test_path` as top-level keys in the structure entry) **MUST** continue to be supported via the `resolvePath()` fallback.
S007-IF-005.c `name` **MUST** be a non-empty string.

### file_map Config Schema

S007-IF-006 [P1] The `file_map` key within a structure block **MUST** be an associative map where:

```yaml
file_map:
  "Auth/LoginService.php": "Auth/LoginServiceTest.php"
  "Legacy/OldHelper.php": "Helpers/OldHelperTest.php"
```

S007-IF-006.a Keys **MUST** be paths relative to the structure's `paths.source` directory.
S007-IF-006.b Values **MUST** be paths relative to the structure's `paths.test` directory.
S007-IF-006.c If `file_map` is absent, `null`, or not an array, it **MUST** be treated as an empty map.
S007-IF-006.d Paths in `file_map` **MUST** use forward slashes as directory separators.
