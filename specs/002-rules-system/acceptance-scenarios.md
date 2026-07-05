# S002 Acceptance Scenarios

### S002-AS-001 Test exists rule passes when test file exists [P1]

**Given** a source file `app/Services/FooService.php` exists
**And** a test file `tests/Unit/Services/FooServiceTest.php` exists
**When** the `test-exists` rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "Y"`

### S002-AS-002 Test exists rule fails when test file is missing [P1]

**Given** a source file `app/Services/BarService.php` exists
**And** no test file exists at the expected path `tests/Unit/Services/BarServiceTest.php`
**When** the `test-exists` rule evaluates the context
**Then** the result **MUST** have `passed = false`, `value = "N"`, and `error = "Test file not found"`

### S002-AS-003 Coverage link detected via pest-covers linker [P1]

**Given** a test file exists and contains `covers(App\Services\FooService::class)`
**And** the `enforce-coverage-link` rule is configured with `linkers: [pest-covers]`
**When** the rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "Y"`

### S002-AS-004 Coverage link detected via php-attribute linker [P1]

**Given** a test file exists and contains `#[CoversClass(App\Services\FooService::class)]`
**And** the `enforce-coverage-link` rule is configured with `linkers: [php-attribute]`
**When** the rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "Y"`

### S002-AS-005 Minimum coverage passes when at threshold [P1]

**Given** a source file has `coveragePercent = 80.0`
**And** the `minimum-coverage` rule is configured with `min: 80`
**When** the rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "80%"`

### S002-AS-006 Minimum coverage fails when below threshold [P1]

**Given** a source file has `coveragePercent = 79.99`
**And** the `minimum-coverage` rule is configured with `min: 80`
**When** the rule evaluates the context
**Then** the result **MUST** have `passed = false` and `error` containing `"below minimum 80%"`

### S002-AS-007 Matched coverage computed from per-line attribution data [P2]

**Given** a source file has 10 total executable lines
**And** `lineCoverage` shows 7 lines covered by tests containing `FooServiceTest`
**And** 3 additional lines covered only by other tests
**And** `expectedTestClassName = "FooServiceTest"`
**And** the `matched-coverage` rule is configured with `min: 60`
**When** the rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "70%"`

### S002-AS-008 Coverage attribution shows total and other test counts [P2]

**Given** a source file is covered by 5 tests total
**And** 2 of those tests contain `FooServiceTest` in their name
**And** `expectedTestClassName = "FooServiceTest"`
**When** the `coverage-attribution` rule evaluates the context
**Then** the result **MUST** have `passed = true` and `value = "5|3"` (5 total, 3 other)

### S002-AS-009 Unknown rule name throws descriptive exception [P1]

**Given** a `parity.yaml` structure block lists a rule `"nonexistent-rule"`
**And** no rule with that name is registered
**When** the registry resolves the rule configuration
**Then** an `InvalidArgumentException` **MUST** be thrown
**And** the exception message **MUST** contain `"Unknown parity rule: 'nonexistent-rule'"`
**And** the message **MUST** list available rule names

### S002-AS-010 Invalid parameters throw descriptive exception [P1]

**Given** the `minimum-coverage` rule requires a numeric `min` parameter
**And** the configuration provides `min: "not-a-number"`
**When** the registry validates the parameters
**Then** an `InvalidArgumentException` **MUST** be thrown
**And** the message **MUST** identify the rule name and the invalid parameter

### S002-AS-011 test-exists is auto-prepended when not listed [P1]

**Given** a structure block's `rules` array contains only `["minimum-coverage: { min: 80 }"]`
**And** `test-exists` is not listed
**When** the rules are resolved for that structure
**Then** the resolved rule list **MUST** begin with the `test-exists` rule
**And** the total resolved list **MUST** contain `test-exists` exactly once

### S002-AS-012 PHPUnit XML coverage triggers auto-append of matched-coverage and coverage-attribution [P2]

**Given** coverage data was loaded from a PHPUnit XML source (directory with `index.xml`)
**And** the structure's `rules` array does not include `matched-coverage` or `coverage-attribution`
**When** the rules are resolved for that structure
**Then** `matched-coverage` **MUST** appear in the resolved list
**And** `coverage-attribution` **MUST** appear in the resolved list

### S002-AS-013 Enforced rule failure causes check command to return non-zero exit [P1]

**Given** at least one source file fails an enforced rule (e.g. `test-exists` returns `passed = false`)
**When** the `check` command finishes processing all structures
**Then** the command **MUST** return exit code 1 (FAILURE)

### S002-AS-014 Informational rule failure does not affect exit code [P2]

**Given** all enforced rules pass for every source file
**And** the `coverage-attribution` rule (informational) reports data
**When** the `check` command finishes processing all structures
**Then** the command **MUST** return exit code 0 (SUCCESS)

### S002-AS-015 Custom plugin rule is evaluated identically to built-in rules [P1]

**Given** a custom rule implementing `RuleInterface` is registered via the plugin system (S005)
**And** the custom rule is listed in a structure's `rules` array
**When** the check command evaluates source files
**Then** the custom rule **MUST** receive the same `RuleContext` as built-in rules
**And** its `columnHeader()`, `formatCell()`, and `isEnforced()` **MUST** be respected identically
