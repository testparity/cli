# S008 Success Criteria

| ID | Criterion |
|----|-----------|
| S008-SC-001 | The output of `parity check --format=json` **MUST** be valid JSON parseable by `json_decode()` without error for every possible project state (passing, failing, empty, error). Piping stdout to `jq .` **MUST** succeed with exit code 0. No ANSI escape sequences, warnings, or non-JSON bytes are permitted in stdout when `--format=json` is active. |
| S008-SC-002 | Running `parity check` on the same project state 100 times in sequence **MUST** produce the same exit code every time. The exit code computation is deterministic: the same set of rule results and global coverage values always yields the same `$hasFailure` boolean. |
| S008-SC-003 | In table mode, files within each structure **MUST** be visually grouped by their parent directory. A directory separator row **MUST** appear before the first file in each new directory group (except for files at the source root). Files within each group **MUST** be sorted alphabetically. |
| S008-SC-004 | If every evaluated rule where `isEnforced()` returns `true` produces `RuleResult::$passed === true` for every file across all structures, and global coverage meets its threshold (or is not configured), then the exit code **MUST** be 0. No informational rule result may cause exit code 1. |
| S008-SC-005 | Given the same set of evaluated `RuleResult` objects and the same `$hasFailure` boolean, switching between `--format=table` and `--format=json` **MUST** produce the same exit code. The format flag controls presentation only, never pass/fail logic. |
| S008-SC-006 | In JSON mode, the `rules` object for each file **MUST** contain an entry for every rule that was evaluated against that file. No evaluated rule may be omitted from JSON output, including informational rules and rules with null `columnHeader()`. The count of keys in the `rules` object **MUST** equal the count of resolved rules for that structure. |
