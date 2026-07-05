# S006 Success Criteria

| ID | Criterion |
|----|-----------|
| S006-SC-001 | Every optional config key **MUST** have a documented default value, and `Settings::fromConfig([])` (empty config) **MUST** produce a valid `Settings` object with all defaults applied without throwing any exception. |
| S006-SC-002 | All properties on the `Settings` DTO **MUST** be declared `public readonly`. After construction, no property value can be changed. Attempting to assign a value to any property **MUST** produce a PHP fatal error. |
| S006-SC-003 | The output of `parity init` **MUST** round-trip: parsing the generated file with `Yaml::parse()` and passing the result to `Settings::fromConfig()` **MUST** produce a valid `Settings` object. Running `parity check` against the init template (given valid source/test directories and coverage data) **MUST NOT** produce any config-related errors. |
| S006-SC-004 | A legacy-format structure entry (using `source_path`/`test_path`/`enforce_attribute`/`min_coverage`) **MUST** produce the same resolved rules as an equivalent modern-format entry (using `paths`/`rules`). Given identical source files and coverage data, the two formats **MUST** produce identical `RuleResult` outputs. |
| S006-SC-005 | When a config file contains invalid content (empty file, non-array YAML, unreadable file, missing Symfony Yaml), the system **MUST** produce an actionable error message that tells the user what went wrong and how to fix it. The system **MUST NOT** produce an uncaught exception or stack trace for these cases. |
