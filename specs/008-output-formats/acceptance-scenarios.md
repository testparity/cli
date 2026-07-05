# S008 Acceptance Scenarios

### S008-AS-001 Table mode: all rules pass [P1]

**Given** a valid `parity.yaml` with one structure entry containing three source files, all with matching test files, all passing `test-exists`, `enforce-coverage-link`, and `minimum-coverage` rules
**When** the user runs `parity check`
**Then** the output contains a structure title and path subtitles, a table with `Source`, `Test`, rule columns, and `OK` as headers, all file rows show green pass indicators, the `OK` column shows `Y` (green) for every row, and the coverage summary table follows. Exit code is 0.

### S008-AS-002 Table mode: mixed pass/fail [P1]

**Given** a valid project with five source files where two fail `test-exists` and one fails `minimum-coverage`
**When** the user runs `parity check`
**Then** the failing files show red `N` in the relevant rule columns and `N` (red) in the `OK` column, passing files show green indicators and `Y` in the `OK` column, files are sorted alphabetically, directory separators appear between different directory groups, and exit code is 1.

### S008-AS-003 JSON mode: all rules pass [P1]

**Given** a valid project where all enforced rules pass and global coverage meets the configured threshold
**When** the user runs `parity check --format=json`
**Then** stdout contains exactly one JSON document with `"passed": true`, `global_coverage` as a number, `structures` containing file entries each with `"passed": true`, and `rules` objects for each evaluated rule with `"passed": true`. No non-JSON text appears in stdout. Exit code is 0.

### S008-AS-004 JSON mode: violations present [P1]

**Given** a valid project where two files fail enforced rules
**When** the user runs `parity check --format=json`
**Then** the JSON document has `"passed": false` at the top level, the failing files have `"passed": false` in their file entry and the failing rules have `"passed": false` with a non-null `error` string, passing files have `"passed": true`. Exit code is 1.

### S008-AS-005 Exit code 0 on clean run [P1]

**Given** a fully compliant project where every enforced rule passes and global coverage is above threshold
**When** the user runs `parity check` (or `parity check --format=json`)
**Then** the exit code is 0 in both cases.

### S008-AS-006 Exit code 1 on enforced failure [P1]

**Given** a project where exactly one file fails `minimum-coverage` (an enforced rule) but all other files pass all rules
**When** the user runs `parity check`
**Then** the exit code is 1. Running again with `--format=json` also produces exit code 1.

### S008-AS-007 Informational rule does not cause exit 1 [P1]

**Given** a project where all enforced rules pass for all files but `coverage-attribution` (an informational rule) reports non-zero "Other" coverage
**When** the user runs `parity check`
**Then** the `coverage-attribution` columns show data in the table, the `OK` column shows `Y` for all files, and the exit code is 0. In JSON mode, `"passed": true` at the top level.

### S008-AS-008 --show-tests in table mode [P2]

**Given** a project with PHPUnit XML coverage data that includes test method names, and three tests covering `AuthService.php`
**When** the user runs `parity check --show-tests`
**Then** the coverage attribution column(s) display the individual test method names rather than just a count. The `--show-tests` flag has no effect on exit code.

### S008-AS-009 Multiple structures produce multiple tables [P1]

**Given** a `parity.yaml` with three structure entries: `Unit Services`, `Unit Actions`, and `Feature Tests`
**When** the user runs `parity check`
**Then** three separate tables are rendered, each preceded by its own `Structure: {name}` title and source/test path subtitles, followed by a single combined `Summary` section at the end. Exit code reflects the aggregate result.

### S008-AS-010 JSON output parseable by jq [P1]

**Given** any valid project configuration
**When** the user runs `parity check --format=json | jq .`
**Then** `jq` exits successfully (exit code 0), confirming the output is valid JSON. No parse errors occur.

### S008-AS-011 Global coverage below threshold in JSON [P1]

**Given** a project with `min_coverage_global: 80` and actual global coverage of 65%, with all per-file enforced rules passing
**When** the user runs `parity check --format=json`
**Then** the JSON has `"passed": false`, `"global_coverage": 65`, `"min_coverage_global": 80`, and exit code is 1. No text messages about global coverage appear in stdout.

### S008-AS-012 Piped output has no ANSI codes [P2]

**Given** a valid project with mixed pass/fail results
**When** the user runs `parity check > output.txt`
**Then** `output.txt` contains no ANSI escape sequences (no `\033[` or `\e[` patterns). Symfony Console's TTY detection suppresses decoration when stdout is not a terminal.
