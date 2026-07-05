# Rules Reference

Specs: S002, S004

For parameter validation, table columns, JSON rule output, and linker behavior, see `docs/REFERENCE.md`.

| Rule | Enforced | Purpose |
|------|----------|---------|
| `test-exists` | Yes | Confirms the expected test file exists for each source file. |
| `enforce-coverage-link` | Yes | Confirms tests declare the source they cover through supported linkers. |
| `minimum-coverage` | Yes | Confirms all-test per-file coverage meets the configured threshold. |
| `matched-coverage` | Yes when configured with `min` | Confirms the matching test file covers enough executable lines. |
| `coverage-attribution` | No | Reports how many tests cover each file and how many are incidental. |

`minimum-coverage` and `matched-coverage` accept `min` values from `0` to `100`.
