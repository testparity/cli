# S002 Interface Requirements

### RuleInterface Method Signatures

S002-IF-001 [P1] The `RuleInterface` **MUST** define the following PHP interface in namespace `App\Rules`:

```php
interface RuleInterface
{
    public function name(): string;
    public function parameters(): array;
    public function evaluate(RuleContext $context, array $params): RuleResult;
    public function columnHeader(): ?string;
    public function formatCell(RuleResult $result): string;
    public function isEnforced(): bool;
}
```

S002-IF-001.a `name()` **MUST** return a non-empty string. By convention, kebab-case is used (e.g. `test-exists`).
S002-IF-001.b `parameters()` return type **MUST** be `array<string, string|array>` where keys are parameter names and values are pipe-delimited validation rule strings (e.g. `'required|numeric|min:0|max:100'`).
S002-IF-001.c `evaluate()` **MUST** be a pure function of its inputs: the same `RuleContext` and `params` **MUST** produce the same `RuleResult`. Side effects (I/O, state mutation) are not prohibited but are discouraged.
S002-IF-001.d `columnHeader()` **MUST** return `null` for rules that produce no visible table column.
S002-IF-001.e `formatCell()` **MAY** use Symfony Console `<fg=color>` tags for colored output.
S002-IF-001.f `isEnforced()` **MUST** return a constant value (not context-dependent). A rule is either always enforced or never enforced.

### RuleResult Static Constructors

S002-IF-002 [P1] `RuleResult` **MUST** expose the following API:

```php
class RuleResult
{
    public readonly bool $passed;
    public readonly ?string $value;
    public readonly ?string $error;

    public static function pass(?string $value = null): self;
    public static function fail(string $error, ?string $value = null): self;
    public static function skip(?string $value = null): self;
}
```

S002-IF-002.a `pass()` **MUST** set `passed = true`, `error = null`.
S002-IF-002.b `fail()` **MUST** set `passed = false`.
S002-IF-002.c `skip()` **MUST** set `passed = true`, `error = null` (semantically equivalent to pass but signals non-applicability).
S002-IF-002.d The constructor **MAY** be public but the static factories **SHOULD** be the primary creation mechanism.

### RuleContext Constructor Properties

S002-IF-003 [P1] `RuleContext` **MUST** expose the following readonly properties, all set via the constructor:

```php
class RuleContext
{
    public readonly string $sourceAbsolutePath;
    public readonly string $sourceRelativePath;
    public readonly string $expectedSourceFqcn;
    public readonly ?string $testAbsolutePath;
    public readonly ?string $testRelativePath;
    public readonly bool $testExists;
    public readonly ?string $testContent;
    public readonly float $coveragePercent;
    public readonly ?float $matchedCoveragePercent;
    public readonly array $coveringTests;
    public readonly string $projectRoot;
    public readonly string $expectedTestClassName;   // default: ''
    public readonly array $lineCoverage;             // default: []
    public readonly int $totalExecutableLines;       // default: 0
}
```

S002-IF-003.a When no test file exists, `testAbsolutePath`, `testRelativePath`, and `testContent` **MUST** be `null`, and `testExists` **MUST** be `false`.
S002-IF-003.b `coveringTests` **MUST** be an array of fully qualified test method name strings (e.g. `Tests\Unit\FooTest::testBar`).
S002-IF-003.c `lineCoverage` **MUST** be an associative array where keys are line numbers (int) and values are arrays of test name strings that cover that line.
S002-IF-003.d `expectedTestClassName` **MUST** be the short class name of the expected test file (e.g. `FooServiceTest`), derived from the test file path's basename without extension.

### RuleRegistry Public API

S002-IF-004 [P1] `RuleRegistry` **MUST** expose the following public methods:

```php
class RuleRegistry
{
    public function register(RuleInterface $rule): void;
    public function get(string $name): ?RuleInterface;
    public function has(string $name): bool;
    public function all(): array;
    public function resolve(array $ruleConfigs): array;
}
```

S002-IF-004.a `resolve()` **MUST** accept the mixed config formats described in S002-FR-005 and return `list<array{rule: RuleInterface, params: array<string, mixed>}>`.
S002-IF-004.b `resolve()` **MUST** throw `\InvalidArgumentException` for unknown rule names (S002-FR-005.e) and invalid parameters (S002-FR-006).

### YAML Configuration Schema

S002-IF-005 [P2] The `rules` key within a structure block in `parity.yaml` **MUST** accept the following formats:

```yaml
# Format 1: String (rule name only, no params)
rules:
  - test-exists
  - enforce-coverage-link

# Format 2: Map with rule name as key, params as nested map
rules:
  - minimum-coverage:
      min: 80
  - enforce-coverage-link:
      linkers: [pest-covers, php-attribute]

# Format 3: Map with explicit 'name' key
rules:
  - name: minimum-coverage
    min: 80

# Mixed formats compose freely
rules:
  - test-exists
  - minimum-coverage:
      min: 80
  - enforce-coverage-link:
      linkers: [pest-covers]
  - coverage-attribution
```

S002-IF-005.a Format 2 (key-as-name) **MUST** be the documented primary format.
S002-IF-005.b Format 3 (explicit `name` key) **MUST** be supported for backward compatibility.
S002-IF-005.c All three formats **MUST** be freely intermixable within a single `rules` array.

### Built-in Rule Parameter Specifications

S002-IF-006 [P1] Each built-in rule **MUST** declare its parameters as follows:

| Rule | Parameter | Validation Spec | Default |
|------|-----------|----------------|---------|
| `test-exists` | _(none)_ | `[]` | N/A |
| `enforce-coverage-link` | `linkers` | `sometimes\|array` | `null` (all linkers / auto) |
| `enforce-coverage-link` | `linkers.*` | `string\|in:auto,pest-covers,php-attribute` | N/A |
| `enforce-coverage-link` | `attribute` | `sometimes\|string` | `PHPUnit\Framework\Attributes\CoversClass` |
| `minimum-coverage` | `min` | `required\|numeric\|min:0\|max:100` | N/A (required) |
| `matched-coverage` | `min` | `sometimes\|numeric\|min:0\|max:100` | `null` (informational when absent) |
| `coverage-attribution` | `show_names` | `sometimes` | N/A |
