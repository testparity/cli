# S007 Functional Requirements

### Path to FQCN Conversion

S007-FR-001 [P1] `NamespaceHelper::pathToFqcn()` **MUST** convert a relative file path to a fully qualified class name by matching the first path segment against configured `namespace_roots` and replacing directory separators with the configured namespace separator.
S007-FR-001.a Given `namespace_roots = {'app' => 'App'}` and input `app/Services/Auth/LoginService.php`, the result **MUST** be `App\Services\Auth\LoginService`.
S007-FR-001.b Given `namespace_roots = {'tests' => 'Tests'}` and input `tests/Unit/Actions/StoreTest.php`, the result **MUST** be `Tests\Unit\Actions\StoreTest`.
S007-FR-001.c The method **MUST** iterate all configured namespace roots and use the first one whose directory key matches the first segment of the path (case-insensitive comparison).

S007-FR-002 [P1] Namespace root matching on the first path segment **MUST** be case-insensitive.
S007-FR-002.a Given `namespace_roots = {'app' => 'App'}` and input `App/Services/Foo.php`, the method **MUST** match the `app` root and return `App\Services\Foo`.
S007-FR-002.b The comparison **MUST** use `strtolower()` on both the path segment and the root key.

S007-FR-003 [P1] When no configured namespace root matches the first path segment, `pathToFqcn()` **MUST** apply a default fallback.
S007-FR-003.a If the first segment is `app` (any case), the namespace prefix **MUST** default to `App`.
S007-FR-003.b If the first segment is `tests` (any case), the namespace prefix **MUST** default to `Tests`.
S007-FR-003.c For any other first segment, the namespace prefix **MUST** be `ucfirst()` of the segment.

S007-FR-004 [P1] `pathToFqcn()` **MUST** strip the configured `sourceExtension` (default `.php`) from the end of the path before conversion.
S007-FR-004.a The extension check **MUST** use `str_ends_with()`.
S007-FR-004.b If the path does not end with the source extension, it **MUST** be converted as-is (no error).

S007-FR-005 [P1] `pathToFqcn()` **MUST** normalize all backslash (`\`) characters in the input to forward slashes (`/`) before processing.

S007-FR-006 [P1] `pathToFqcn()` **MUST** strip any leading forward slash from the input path via `ltrim($path, '/')`.

### Source to Test Path Derivation

S007-FR-007 [P1] `NamespaceHelper::sourcePathToTestPath()` **MUST** derive the expected test file path by replacing the source directory prefix with the test directory prefix, appending `testSuffix` to the base filename, and using `testExtension` as the file extension.
S007-FR-007.a Given `sourcePathToTestPath('app/Services/AuthService.php', 'app/Services', 'tests/Unit/Services')`, the result **MUST** be `tests/Unit/Services/AuthServiceTest.php`.
S007-FR-007.b The method **MUST** accept three string parameters: the full source relative path, the source path base, and the test path base.

S007-FR-008 [P1] The test suffix **MUST** be inserted between the base filename (without extension) and the test extension.
S007-FR-008.a Given base name `AuthService`, test suffix `Test`, and test extension `.php`, the derived test file name **MUST** be `AuthServiceTest.php`.
S007-FR-008.b Given base name `UserController`, test suffix `Spec`, and test extension `.ts`, the derived test file name **MUST** be `UserControllerSpec.ts`.

S007-FR-009 [P1] `sourcePathToTestPath()` **MUST** preserve the subdirectory structure between the source path base and the source file.
S007-FR-009.a Given source path `app/Services/Auth/Providers/LoginProvider.php` and source base `app/Services`, the subdirectory `Auth/Providers/` **MUST** appear in the test path: `tests/Unit/Services/Auth/Providers/LoginProviderTest.php`.

S007-FR-010 [P1] When the source path does not start with the source path base prefix, `sourcePathToTestPath()` **MUST** fall back to placing the test file directly under the test path base using only the basename.
S007-FR-010.a The fallback **MUST** use `basename($path, $sourceExtension)` to extract the filename without extension.
S007-FR-010.b The result **MUST** be `testPathBase / basename + testSuffix + testExtension`.

### file_map Overrides

S007-FR-011 [P1] When a source file's relative path within the structure matches a key in the structure's `file_map`, the mapped value **MUST** be used instead of the algorithmic test path derivation.
S007-FR-011.a The file_map lookup **MUST** occur before any call to `sourcePathToTestPath()`.
S007-FR-011.b If the file_map contains a match, the test path **MUST** be constructed as `testPath / mappedValue`.

S007-FR-012 [P1] `file_map` keys **MUST** be relative to the structure's `paths.source` directory, and values **MUST** be relative to the structure's `paths.test` directory.
S007-FR-012.a Given structure `paths.source: app/Services` and `file_map: { "Auth/LoginService.php": "Auth/LoginServiceTest.php" }`, the source file `app/Services/Auth/LoginService.php` **MUST** map to `tests/Unit/Services/Auth/LoginServiceTest.php` (assuming `paths.test: tests/Unit/Services`).

### Multiple Structure Blocks

S007-FR-013 [P1] Each structure block in the `structure` array **MUST** operate independently with its own `paths.source`, `paths.test`, `rules`, and `file_map`.
S007-FR-013.a A source file appearing in the scan of structure block A **MUST NOT** be affected by the file_map or rules of structure block B.
S007-FR-013.b Structure blocks **MUST** be processed in the order they appear in the config.

### Path Normalization

S007-FR-014 [P1] `NamespaceHelper::normalizeRelativePath()` **MUST** produce a canonical form of a relative path by:
S007-FR-014.a Replacing all backslash (`\`) characters with forward slashes (`/`).
S007-FR-014.b Collapsing consecutive slashes (`//`, `///`, etc.) into a single slash.
S007-FR-014.c Stripping leading and trailing slashes.

### Configurable Settings

S007-FR-015 [P2] The namespace separator used by `pathToFqcn()` **MUST** be configurable via `Settings::namespaceSeparator`.
S007-FR-015.a The default namespace separator **MUST** be `\` (PHP backslash).
S007-FR-015.b For non-PHP languages, the separator **MAY** be `.` (Java/Python) or any other single-character string.

S007-FR-016 [P2] Source extension, test suffix, and test extension **MUST** each be independently configurable via `Settings`.
S007-FR-016.a `sourceExtension` defaults to `.php`.
S007-FR-016.b `testSuffix` defaults to `Test`.
S007-FR-016.c `testExtension` defaults to the value of `sourceExtension` if not explicitly set.

### Constructor

S007-FR-017 [P1] `NamespaceHelper` **MUST** support two construction modes:
S007-FR-017.a Construction with a `Settings` object: all configuration values **MUST** be read from the Settings instance.
S007-FR-017.b Construction with a raw `$roots` array (and no Settings): defaults **MUST** be applied for all other settings (`sourceExtension: .php`, `testSuffix: Test`, `testExtension: .php`, `namespaceSeparator: \`).
S007-FR-017.c If neither `$roots` nor `$settings` is provided, the default roots **MUST** be `['app' => 'App', 'tests' => 'Tests']`.
