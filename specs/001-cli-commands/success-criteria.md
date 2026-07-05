# S001 Success Criteria

| ID | Criterion |
|----|-----------|
| S001-SC-001 | Running `parity check` on a fully compliant project (all rules pass, global coverage above threshold) **MUST** always produce exit code 0. Running it 100 times in sequence **MUST** produce exit code 0 every time (deterministic). |
| S001-SC-002 | The output of `parity check --format=json` **MUST** be valid JSON parseable by `json_decode()` without error. Piping stdout to `jq .` **MUST** succeed. No non-JSON bytes (warnings, ANSI codes, newlines before/after) are permitted in stdout. |
| S001-SC-003 | In table mode, files **MUST** be visually grouped by their parent directory within each structure. A directory separator row **MUST** appear before the first file in each new directory group. |
| S001-SC-004 | When zero enforced rule violations exist across all structures and global coverage (if configured) meets its threshold, the exit code **MUST** be 0. |
| S001-SC-005 | When at least one enforced rule violation exists in any structure, or global coverage is below its configured threshold, the exit code **MUST** be 1. |
