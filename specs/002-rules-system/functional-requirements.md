# S002 Functional Requirements

### RuleInterface Contract

S002-FR-001 [P1] Every rule **MUST** implement the `RuleInterface` contract, providing all six methods: `name()`, `parameters()`, `evaluate()`, `columnHeader()`, `formatCell()`, `isEnforced()`.
S002-FR-001.a The `name()` method **MUST** return a unique kebab-case string identifier (e.g. `test-exists`, `minimum-coverage`).
S002-FR-001.b The `parameters()` method **MUST** return an associative array mapping parameter names to Laravel-style validation rule strings, or an empty array if the rule accepts no parameters.
S002-FR-001.c The `evaluate()` method **MUST** accept a `RuleContext` and a params array, and **MUST** return a `RuleResult`.
S002-FR-001.d The `columnHeader()` method **MUST** return a short string for table display, or `null` if the rule produces no visible column.
S002-FR-001.e The `formatCell()` method **MUST** accept a `RuleResult` and return a string suitable for Symfony Console output (may contain `<fg=...>` tags).
S002-FR-001.f The `isEnforced()` method **MUST** return `true` if rule failures contribute to the overall pass/fail status, or `false` for informational-only rules.

### RuleResult Value Object

S002-FR-002 [P1] `RuleResult` **MUST** be an immutable value object with three readonly properties: `passed` (bool), `value` (?string), and `error` (?string).
S002-FR-002.a `RuleResult::pass(?string $value)` **MUST** return an instance with `passed = true`, the given `value`, and `error = null`.
S002-FR-002.b `RuleResult::fail(string $error, ?string $value)` **MUST** return an instance with `passed = false`, the given `error`, and the optional `value`.
S002-FR-002.c `RuleResult::skip(?string $value)` **MUST** return an instance with `passed = true` and the given `value`, used when a rule is not applicable to the current context.

### RuleContext Immutable Data Carrier

S002-FR-003 [P1] `RuleContext` **MUST** be an immutable data transfer object carrying all data a rule needs to evaluate a single source file.
S002-FR-003.a `RuleContext` **MUST** include the source file's absolute path, relative path, and expected FQCN.
S002-FR-003.b `RuleContext` **MUST** include the test file's absolute path, relative path, existence flag, and content (all nullable when no test exists).
S002-FR-003.c `RuleContext` **MUST** include coverage data: `coveragePercent` (float), `matchedCoveragePercent` (?float), and `coveringTests` (array of test name strings).
S002-FR-003.d `RuleContext` **MUST** include the `projectRoot` path.
S002-FR-003.e `RuleContext` **SHOULD** include `expectedTestClassName`, `lineCoverage` (per-line test attribution array), and `totalExecutableLines` for PHPUnit XML coverage support.
S002-FR-003.f All `RuleContext` properties **MUST** be declared `readonly`.

### RuleRegistry Registration and Lookup

S002-FR-004 [P1] The `RuleRegistry` **MUST** store rules indexed by their `name()` return value.
S002-FR-004.a `register(RuleInterface $rule)` **MUST** add the rule, keyed by `$rule->name()`.
S002-FR-004.b `get(string $name)` **MUST** return the `RuleInterface` instance for the given name, or `null` if not found.
S002-FR-004.c `has(string $name)` **MUST** return `true` if a rule with the given name is registered, `false` otherwise.
S002-FR-004.d `all()` **MUST** return all registered rules as an associative array keyed by name.
S002-FR-004.e If a rule is registered with the same name as an existing rule, the new rule **MUST** replace the old one (last-write-wins).

### RuleRegistry Configuration Resolution

S002-FR-005 [P1] The `resolve(array $ruleConfigs)` method **MUST** accept a mixed array of rule configurations and return a list of `['rule' => RuleInterface, 'params' => array]` tuples.
S002-FR-005.a String entries (e.g. `"test-exists"`) **MUST** be resolved as a rule name with an empty params array.
S002-FR-005.b Map entries with a `name` key (e.g. `{ name: minimum-coverage, min: 80 }`) **MUST** use the `name` value as the rule name and the remaining keys as params.
S002-FR-005.c Map entries without a `name` key (e.g. `{ minimum-coverage: { min: 80 } }`) **MUST** use the first key as the rule name and its value as params.
S002-FR-005.d If a map entry's value is not an array, `resolve()` **MUST** treat params as an empty array.
S002-FR-005.e If a referenced rule name is not registered, `resolve()` **MUST** throw an `InvalidArgumentException` listing the unknown name and all available rule names.

### Parameter Validation

S002-FR-006 [P1] The registry **MUST** validate resolved params against the rule's `parameters()` spec before returning resolved tuples.
S002-FR-006.a If a parameter is marked `required` and the value is `null` (absent), the registry **MUST** throw an `InvalidArgumentException` naming the rule and the missing parameter.
S002-FR-006.b If a parameter is marked `numeric` and the value is not numeric, the registry **MUST** throw an `InvalidArgumentException`.
S002-FR-006.c If a parameter is marked `string` and the value is not a string, the registry **MUST** throw an `InvalidArgumentException`.
S002-FR-006.d If a parameter is marked `array` and the value is not an array, the registry **MUST** throw an `InvalidArgumentException`.
S002-FR-006.e Nested parameter specs (keys containing `.`) **MUST** be skipped during top-level validation.
S002-FR-006.f Parameters marked `sometimes` **MUST** only be validated when present; absence is not an error.

### Built-in Rule: test-exists

S002-FR-007 [P1] The `test-exists` rule **MUST** check whether the expected test file exists for a given source file.
S002-FR-007.a If `RuleContext::testExists` is `true`, `evaluate()` **MUST** return `RuleResult::pass('Y')`.
S002-FR-007.b If `RuleContext::testExists` is `false`, `evaluate()` **MUST** return `RuleResult::fail('Test file not found', 'N')`.
S002-FR-007.c `name()` **MUST** return `"test-exists"`.
S002-FR-007.d `parameters()` **MUST** return an empty array (no configurable parameters).
S002-FR-007.e `columnHeader()` **MUST** return the unicode character `"exists"` (displayed as the symbol used for existence).
S002-FR-007.f `isEnforced()` **MUST** return `true`.

### Built-in Rule: enforce-coverage-link

S002-FR-008 [P1] The `enforce-coverage-link` rule **MUST** verify that a test file declares coverage of the source class via a recognized linker mechanism.
S002-FR-008.a If no test file exists (`testExists` is false or `testContent` is null), `evaluate()` **MUST** return `RuleResult::pass('-')` (skip).
S002-FR-008.b The rule **MUST** accept an optional `linkers` parameter: an array of strings from `[auto, pest-covers, php-attribute]`.
S002-FR-008.c The rule **MUST** accept an optional `attribute` parameter: a string specifying a custom attribute FQCN (default: `PHPUnit\Framework\Attributes\CoversClass`).
S002-FR-008.d When linkers are not specified, the rule **MUST** default to all available linkers (equivalent to `auto`).
S002-FR-008.e If the coverage link is found, `evaluate()` **MUST** return `RuleResult::pass('Y')`.
S002-FR-008.f If no coverage link is found, `evaluate()` **MUST** return `RuleResult::fail()` with an error message and value `'N'`.
S002-FR-008.g `isEnforced()` **MUST** return `true`.

### Built-in Rule: minimum-coverage

S002-FR-009 [P1] The `minimum-coverage` rule **MUST** check that a source file's total coverage percentage meets or exceeds a configurable threshold.
S002-FR-009.a The rule **MUST** require a `min` parameter: a numeric value between 0 and 100 (inclusive).
S002-FR-009.b If `coveragePercent >= min`, `evaluate()` **MUST** return `RuleResult::pass()` with the percentage as a string (e.g. `"85%"`).
S002-FR-009.c If `coveragePercent < min`, `evaluate()` **MUST** return `RuleResult::fail()` with a descriptive error message and the percentage as value.
S002-FR-009.d `isEnforced()` **MUST** return `true`.

### Built-in Rule: matched-coverage

S002-FR-010 [P2] The `matched-coverage` rule **MUST** compute coverage contributed only by the matching test class, using per-line coverage attribution from PHPUnit XML data.
S002-FR-010.a If no test exists or `expectedTestClassName` is empty, `evaluate()` **MUST** return `RuleResult::pass('-')` (skip).
S002-FR-010.b If `totalExecutableLines` is 0, `evaluate()` **MUST** return `RuleResult::pass('-')` (skip).
S002-FR-010.c The rule **MUST** iterate `lineCoverage` and count lines where at least one covering test name contains `expectedTestClassName`.
S002-FR-010.d The matched coverage percentage **MUST** be calculated as `round(100.0 * matchedLines / totalExecutableLines, 2)`.
S002-FR-010.e The rule **MUST** accept an optional `min` parameter (numeric, 0-100). When `min` is set and matched coverage is below it, `evaluate()` **MUST** return `RuleResult::fail()`.
S002-FR-010.f When `min` is not set, the rule **MUST** always pass (informational mode).
S002-FR-010.g `isEnforced()` **MUST** return `true`.

### Built-in Rule: coverage-attribution

S002-FR-011 [P2] The `coverage-attribution` rule **MUST** report how many tests cover a source file and how many of those are non-matching (other) tests.
S002-FR-011.a `evaluate()` **MUST** always return `RuleResult::pass()` (this rule never fails).
S002-FR-011.b The result value **MUST** be formatted as `"totalCount|otherCount"` (pipe-separated integers).
S002-FR-011.c "Other" tests are those in `coveringTests` whose name does not contain `expectedTestClassName`.
S002-FR-011.d If `expectedTestClassName` is empty, all covering tests **MUST** be counted as "other".
S002-FR-011.e `isEnforced()` **MUST** return `false`.
S002-FR-011.f The rule **MUST** provide a `formatOtherCell(RuleResult)` method for rendering the second ("Other") column, in addition to the standard `formatCell()`.

### Enforcement vs Informational

S002-FR-012 [P1] The check command **MUST** determine the overall pass/fail status of a source file by evaluating all configured rules and checking whether any **enforced** rule returned a failing result.
S002-FR-012.a A source file **MUST** be considered "failed" if and only if at least one rule where `isEnforced()` returns `true` has a `RuleResult` with `passed = false`.
S002-FR-012.b Rules where `isEnforced()` returns `false` **MUST NOT** affect the file's pass/fail status regardless of their result.
S002-FR-012.c The overall check command **MUST** return a non-zero exit code if any file in any structure has a failure.

### Auto-Active test-exists Rule

S002-FR-013 [P1] The `test-exists` rule **MUST** be automatically prepended to the resolved rule list for every structure block, even if not explicitly listed in the `rules` configuration.
S002-FR-013.a If the user's `rules` array already contains `test-exists`, it **MUST NOT** be duplicated.

### Auto-Appended PHPUnit XML Rules

S002-FR-014 [P2] When PHPUnit XML coverage data is detected, the system **SHOULD** auto-append `matched-coverage` and `coverage-attribution` rules to each structure's rule list if not already present.
S002-FR-014.a Auto-appending **MUST NOT** duplicate rules already explicitly listed by the user.

### Per-Structure Rule Configuration

S002-FR-015 [P1] Each structure block in `parity.yaml` **MUST** support its own `rules` array, allowing different structures to have different rules and parameters.
S002-FR-015.a The `rules` array **MUST** support both string entries (rule name only) and map entries (rule name with parameters).

### Legacy Configuration Format

S002-FR-016 [P2] When a structure block lacks a `rules` array, the system **SHOULD** fall back to legacy configuration keys: `enforce_coverage_link`, `enforce_attribute`, `min_coverage`, and `min_matched_coverage`.
S002-FR-016.a Legacy `enforce_coverage_link: true` **MUST** be converted to an `enforce-coverage-link` rule entry.
S002-FR-016.b Legacy `enforce_attribute: "Fully\\Qualified\\Attribute"` **MUST** be passed as the `attribute` parameter to `enforce-coverage-link`.
S002-FR-016.c Legacy `min_coverage` values **MUST** be passed as the `min` parameter to `minimum-coverage`.
S002-FR-016.d Legacy `min_matched_coverage` values **MUST** be passed as the `min` parameter to `matched-coverage`.
S002-FR-016.e When no legacy keys are present, the global defaults from S006 (Settings) **MUST** be used.

### Table Output Formatting

S002-FR-017 [P1] Each rule with a non-null `columnHeader()` **MUST** contribute a column to the check output table.
S002-FR-017.a The table **MUST** always begin with `Source` and `Test` columns and end with an `OK` column.
S002-FR-017.b Rule columns **MUST** appear between `Test` and `OK` in the order the rules were resolved.
S002-FR-017.c The `coverage-attribution` rule **MUST** contribute two columns: the standard column (total test count, header `#`) and an additional `Other` column.

### JSON Output Formatting

S002-FR-018 [P1] In JSON output mode, each file's rule results **MUST** be included under a `rules` key, keyed by rule name.
S002-FR-018.a Each rule entry **MUST** include `passed` (bool), `value` (?string), and `error` (?string).
