# S004 Interface Requirements

### CoverageLinkerInterface

S004-IF-001 [P1] `CoverageLinkerInterface` **MUST** define the following method signatures:

```php
interface CoverageLinkerInterface
{
    /**
     * Determine whether this linker can handle the given test file.
     *
     * @param string $testFileContent Raw PHP source of the test file
     * @return bool true if this linker recognizes the file's coverage syntax
     */
    public function supports(string $testFileContent): bool;

    /**
     * Extract covered class FQCNs from the test file source.
     *
     * @param string $source        Raw PHP source of the test file
     * @param array<string, string> $useMap  Map of short name => FQCN from use statements
     * @param string|null $namespace        Declared namespace of the test file (null if global)
     * @return list<string> Fully qualified class names this test declares coverage for
     */
    public function extractCoveredClasses(string $source, array $useMap, ?string $namespace): array;

    /**
     * Human-readable linker identifier for error messages and config.
     *
     * @return string Stable name (e.g. 'pest-covers', 'php-attribute')
     */
    public function name(): string;
}
```

S004-IF-001.a `supports()` **MUST** accept the full file content as a single string and return a boolean.
S004-IF-001.b `extractCoveredClasses()` **MUST** accept three parameters: source content, use-map (associative array of alias/short-name to FQCN), and nullable namespace string.
S004-IF-001.c `extractCoveredClasses()` **MUST** return a `list<string>` (sequential integer keys, no gaps). Each element is a fully qualified class name without a leading backslash.
S004-IF-001.d `name()` **MUST** return a non-empty string that is stable across versions (used in config files and error messages).

### CoverageLinkerRegistry Public API

S004-IF-002 [P1] `CoverageLinkerRegistry` **MUST** expose the following public API:

```php
class CoverageLinkerRegistry
{
    /**
     * @param list<CoverageLinkerInterface>|null $linkers  Custom linker list (null = defaults)
     */
    public function __construct(?array $linkers = null);

    /**
     * Default linker set: [PestCoversLinker, PhpAttributeLinker].
     * @return list<CoverageLinkerInterface>
     */
    public static function defaults(): array;

    /**
     * Build a registry from config name strings.
     *
     * @param list<string>|null $linkerNames  e.g. ['pest-covers', 'php-attribute', 'auto']
     * @param string $attributeFqcn           Custom attribute FQCN for PhpAttributeLinker
     */
    public static function fromConfig(
        ?array $linkerNames = null,
        string $attributeFqcn = 'PHPUnit\Framework\Attributes\CoversClass'
    ): self;

    /**
     * Find the first supporting linker and extract covered classes.
     *
     * @return array{linker: string|null, classes: list<string>}
     */
    public function extractCoveredClasses(
        string $source,
        array $useMap,
        ?string $namespace
    ): array;

    /**
     * Check if any registered linker supports this file content.
     */
    public function hasSupport(string $source): bool;
}
```

S004-IF-002.a `extractCoveredClasses()` **MUST** return an associative array with exactly two keys: `linker` (string or null) and `classes` (list of strings).
S004-IF-002.b When no linker matches, `linker` **MUST** be `null` and `classes` **MUST** be an empty array.
S004-IF-002.c `hasSupport()` **MUST** return `true` if any registered linker's `supports()` returns `true` for the given content.

### resolveClassReference Static Method

S004-IF-003 [P1] `PhpAttributeLinker::resolveClassReference()` **MUST** be a public static method shared by both linkers for class name resolution:

```php
/**
 * Resolve a class reference from an attribute/method argument.
 * Supports: 'FQCN', "FQCN", X::class, \FQCN::class
 *
 * @param string $arg              Raw argument text (e.g. "FooService::class", "'App\\Services\\Foo'")
 * @param array<string, string> $useMap  Alias => FQCN from use statements
 * @param string|null $classNamespace   Declared namespace (null if global)
 * @return string|null  Resolved FQCN without leading backslash, or null if unresolvable
 */
public static function resolveClassReference(
    string $arg,
    array $useMap,
    ?string $classNamespace
): ?string;
```

S004-IF-003.a The method **MUST** return `null` for arguments that do not match any recognized format.
S004-IF-003.b The returned FQCN **MUST NOT** have a leading backslash (e.g. `App\Services\Foo`, not `\App\Services\Foo`).
S004-IF-003.c The method **MUST** be usable by both `PestCoversLinker` and `PhpAttributeLinker` (shared resolution logic).

### Config Name Mapping

S004-IF-004 [P2] `CoverageLinkerRegistry::fromConfig()` **MUST** map the following config strings to linker instances:

| Config String | Linker Class | Notes |
|---------------|-------------|-------|
| `pest-covers` | `PestCoversLinker` | Always default-constructed |
| `php-attribute` | `PhpAttributeLinker` | Receives `$attributeFqcn` parameter |
| `auto` | Both (default set) | Equivalent to `null` or `[]` |

S004-IF-004.a Any string not in the table above **MUST** be silently ignored (produces no linker instance and no error).
S004-IF-004.b If all provided names are unrecognized, the resulting registry **MUST** contain zero linkers (empty list) and `extractCoveredClasses()` will return `['linker' => null, 'classes' => []]`.
