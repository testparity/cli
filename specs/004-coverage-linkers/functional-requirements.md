# S004 Functional Requirements

### Interface Contract

S004-FR-001 [P1] Every coverage linker **MUST** implement `CoverageLinkerInterface` with three methods: `supports()`, `extractCoveredClasses()`, and `name()`.
S004-FR-001.a `supports(string $testFileContent): bool` **MUST** determine whether the linker can handle the given test file based solely on its content.
S004-FR-001.b `extractCoveredClasses(string $source, array $useMap, ?string $namespace): array` **MUST** return a `list<string>` of fully qualified class names that the test file declares coverage for.
S004-FR-001.c `name(): string` **MUST** return a stable, human-readable identifier used in error messages and config references (e.g. `pest-covers`, `php-attribute`).

### PestCoversLinker

S004-FR-002 [P1] `PestCoversLinker::supports()` **MUST** return `true` only when the file content contains Pest function calls (`it()`, `test()`, `describe()`, `beforeEach()`, `beforeAll()`) AND does not contain a class declaration.
S004-FR-002.a A class declaration **MUST** be detected via the pattern `class <word> (extends|implements|{)`.
S004-FR-002.b Pest function calls **MUST** be detected via the pattern `(it|test|describe|beforeEach|beforeAll)(`.
S004-FR-002.c If both a class declaration and Pest calls are present, `supports()` **MUST** return `false` (class declaration takes precedence).

S004-FR-003 [P1] `PestCoversLinker::extractCoveredClasses()` **MUST** extract class names from the following Pest coverage declaration patterns:
S004-FR-003.a Chained method call: `->covers(ClassName::class)` and `->coversClass(ClassName::class)`.
S004-FR-003.b File-level function call: `covers(ClassName::class)` (standalone, not chained).
S004-FR-003.c Multiple arguments in a single call: `->covers(Foo::class, Bar::class)`.
S004-FR-003.d Multiple separate calls across the file: each `covers()` call contributes its arguments independently.

### PhpAttributeLinker

S004-FR-004 [P1] `PhpAttributeLinker::supports()` **MUST** return `true` when the file content contains a class declaration (pattern: `class <word>`).
S004-FR-004.a The check **MUST NOT** require the class to extend `TestCase` or any specific parent.

S004-FR-005 [P1] `PhpAttributeLinker::extractCoveredClasses()` **MUST** extract class names from PHP 8 attribute syntax `#[CoversClass(ClassName::class)]`.
S004-FR-005.a The linker **MUST** support multiple `#[CoversClass()]` attributes on the same class.
S004-FR-005.b The attribute name to match **MUST** be derived from the configured FQCN's short name (last segment after `\`).

S004-FR-012 [P1] `PhpAttributeLinker::extractCoveredClasses()` **MUST** only scan the portion of the file before the first `class` keyword.
S004-FR-012.a Attributes appearing inside or after the class body **MUST** be ignored.
S004-FR-012.b This prevents false positives from method-level attributes or test fixture data.

### Class Reference Resolution

S004-FR-006 [P1] The `resolveClassReference()` method **MUST** resolve class arguments in the following formats to fully qualified class names:
S004-FR-006.a `ClassName::class` -- resolve via `$useMap` first, then `$namespace` prefix, then bare name.
S004-FR-006.b `\Full\Qualified\ClassName::class` -- leading backslash indicates global scope; strip the leading backslash and use the rest as the FQCN.
S004-FR-006.c `'Full\Qualified\ClassName'` (single-quoted string) -- unescape double backslashes and use as FQCN.
S004-FR-006.d `"Full\Qualified\ClassName"` (double-quoted string) -- unescape double backslashes and use as FQCN.
S004-FR-006.e `Partial\Path\ClassName::class` -- resolve the first segment via `$useMap`, then append the remaining segments.

S004-FR-006.f When resolving `ClassName::class` without a leading backslash, the resolution order **MUST** be:
1. Look up the first segment of the reference in `$useMap`. If found, substitute and append remaining segments.
2. If not in `$useMap` but `$namespace` is non-null, prepend `$namespace\` to the reference.
3. If neither applies, return the bare reference as-is.

S004-FR-006.g If the argument does not match any recognized format (not `::class`, not a string literal), `resolveClassReference()` **MUST** return `null`.

### Registry and Auto-Detection

S004-FR-007 [P1] `CoverageLinkerRegistry::extractCoveredClasses()` **MUST** iterate registered linkers in order and return the result from the first linker whose `supports()` returns `true`.
S004-FR-007.a If no linker supports the file, **MUST** return `['linker' => null, 'classes' => []]`.
S004-FR-007.b The return type **MUST** be `array{linker: string|null, classes: list<string>}`.

S004-FR-008 [P1] `CoverageLinkerRegistry::fromConfig()` **MUST** construct a registry from an array of linker name strings.
S004-FR-008.a `null`, `[]`, or `['auto']` **MUST** all produce the default set: `[PestCoversLinker, PhpAttributeLinker]`.
S004-FR-008.b `['pest-covers']` **MUST** produce a registry containing only `PestCoversLinker`.
S004-FR-008.c `['php-attribute']` **MUST** produce a registry containing only `PhpAttributeLinker`.
S004-FR-008.d `['pest-covers', 'php-attribute']` **MUST** produce a registry containing both linkers in that order.
S004-FR-008.e Unrecognized linker name strings **MUST** be silently skipped (not cause an error).

### Custom Attribute FQCN

S004-FR-009 [P2] `PhpAttributeLinker` **MUST** accept an optional `$attributeFqcn` constructor parameter that overrides the default `PHPUnit\Framework\Attributes\CoversClass`.
S004-FR-009.a The attribute short name used for regex matching **MUST** be derived from the last `\`-delimited segment of the configured FQCN.
S004-FR-009.b `CoverageLinkerRegistry::fromConfig()` **MUST** accept an `$attributeFqcn` parameter and pass it to `PhpAttributeLinker`.

### Static Analysis Constraint

S004-FR-010 [P1] All linkers **MUST** operate exclusively on file content passed as a string parameter.
S004-FR-010.a Linkers **MUST NOT** execute PHP code, use `eval()`, or invoke the PHP parser/tokenizer.
S004-FR-010.b Linkers **MUST NOT** require autoloading, class loading, or file system access.
S004-FR-010.c Linkers **MUST NOT** use reflection or instantiate the classes referenced in coverage declarations.

### Deduplication

S004-FR-011 [P1] `PestCoversLinker::extractCoveredClasses()` **MUST** return deduplicated results when the same class is referenced multiple times.
S004-FR-011.a Deduplication **MUST** use strict string comparison on the resolved FQCN.
S004-FR-011.b The returned list **MUST** preserve insertion order (first occurrence wins).
