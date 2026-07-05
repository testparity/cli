# S002 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S002-EC-001 | `coveragePercent` is exactly `0.0` and `min` is `0` | `minimum-coverage` **MUST** pass (`0.0 >= 0` is true) |
| S002-EC-002 | `coveragePercent` is exactly `100.0` and `min` is `100` | `minimum-coverage` **MUST** pass |
| S002-EC-003 | No test file exists but `minimum-coverage` rule is active | `minimum-coverage` evaluates with `coveragePercent` from context (may be 0.0); result depends on `min` threshold. Rule does not skip. |
| S002-EC-004 | `totalExecutableLines` is `0` for `matched-coverage` rule | `matched-coverage` **MUST** return `RuleResult::pass('-')` (skip) to avoid division by zero |
| S002-EC-005 | Required parameter `min` is missing from `minimum-coverage` config | `RuleRegistry::resolve()` **MUST** throw `InvalidArgumentException` with message containing `"requires parameter 'min'"` |
| S002-EC-006 | Structure has `rules: []` (empty array) | `test-exists` **MUST** still be auto-prepended; the resolved list contains exactly one rule |
| S002-EC-007 | Same rule listed twice in config (e.g. `[test-exists, test-exists]`) | Both entries resolve independently; the rule evaluates twice. Auto-prepend logic checks only the user's list, so `test-exists` is not added a third time. |
| S002-EC-008 | `min` parameter provided as a string like `"eighty"` | `RuleRegistry` **MUST** throw `InvalidArgumentException` because `"eighty"` is not numeric |
| S002-EC-009 | `coveragePercent` is `79.999` and `min` is `80` | `minimum-coverage` **MUST** fail (`79.999 < 80` is true). Floating-point comparison uses native PHP `>=` operator. |
| S002-EC-010 | Test file exists but `testContent` is `null` (unreadable) | `enforce-coverage-link` **MUST** return `RuleResult::pass('-')` (skip) because it checks `testContent === null` |
| S002-EC-011 | A plugin registers a rule with name `"test-exists"` (same as built-in) | The plugin rule replaces the built-in rule in the registry (last-write-wins per S002-FR-004.e). All subsequent evaluations use the plugin's implementation. |
| S002-EC-012 | PHPUnit XML coverage loaded but `lineCoverage` is empty for a file | `matched-coverage` counts 0 matched lines, resulting in 0% matched coverage. If `min` is set, the rule fails; if not, it passes with `"0%"`. |
