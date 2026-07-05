# S005 Success Criteria

| ID | Criterion |
|----|-----------|
| S005-SC-001 | A custom rule class implementing `RuleInterface` placed in `.parity/plugins/` can be discovered, registered, referenced in `parity.yaml`, evaluated during `check`, displayed in the output table, and influence the pass/fail exit code -- all without modifying any Parity core code. The only integration point is the file's return value. |
| S005-SC-002 | All three discovery paths (project-local, global-user, Composer) independently succeed when their respective sources are present, and independently skip without error when their sources are absent. A test project with plugins in all three locations registers the union of all rules. |
| S005-SC-003 | A broken plugin file (syntax error, throwing constructor, missing class, wrong return type) produces a retrievable warning message via `getWarnings()` and does not prevent other plugins or the rest of the Parity check from running. Verified by loading a mix of valid and broken plugins and asserting all valid rules are registered and all broken ones produce warnings. |
| S005-SC-004 | When a project-local plugin, a global-user plugin, and a Composer plugin all register rules with the same `name()`, the final registered instance is the Composer plugin's (loaded last). Verified by checking `RuleRegistry::get()` returns the expected instance. |
| S005-SC-005 | A Composer package declaring `extra.parity.rules` with valid FQCN classes, proper PSR-4 autoloading, and implementations of `RuleInterface` can be installed via `composer require` and its rules immediately appear in `RuleRegistry` without any manual registration or configuration beyond adding the rule name to `parity.yaml`. |

### Non-Functional

| ID | Criterion |
|----|-----------|
| S005-NF-001 | Plugin loading across all three discovery paths **SHOULD** complete in under 100ms for a project with up to 20 plugin files and 10 Composer plugin packages. Plugin loading is I/O-bound (filesystem reads) and **MUST NOT** introduce observable latency to the `check` command. |
| S005-NF-002 | Plugins run unsandboxed in the same PHP process. The documentation (S005-IF-004) **MUST** clearly state that installing a plugin is equivalent to running arbitrary PHP code. Users **SHOULD** only install plugins from trusted sources. This is analogous to Composer packages -- trust is delegated to the user. |
