# S002 Success Criteria

| ID | Criterion |
|----|-----------|
| S002-SC-001 | Each of the five built-in rules (`test-exists`, `enforce-coverage-link`, `minimum-coverage`, `matched-coverage`, `coverage-attribution`) can be unit-tested in isolation by constructing a `RuleContext` and calling `evaluate()` without any filesystem, database, or service dependencies (except `enforce-coverage-link` which delegates to S004 linkers). |
| S002-SC-002 | A custom rule class implementing `RuleInterface` can be registered in `RuleRegistry`, resolved from a YAML config array, and evaluated in the check pipeline without modifying any Parity core code. The only integration point is `RuleRegistry::register()`. |
| S002-SC-003 | Providing an unknown rule name, a missing required parameter, or a parameter of the wrong type to `RuleRegistry::resolve()` always throws an `InvalidArgumentException` with a message that identifies the specific rule and parameter at fault. |
| S002-SC-004 | When a source file passes all enforced rules but has informational rule data (e.g. `coverage-attribution` reports `"5|3"`), the file's overall status is "passed" and the check command exit code is 0. When a source file fails any enforced rule, the file's overall status is "failed" and the check command exit code is 1. |
