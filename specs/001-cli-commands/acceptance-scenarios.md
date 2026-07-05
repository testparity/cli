# S001 Acceptance Scenarios

### S001-AS-001 Happy path: all checks pass [P1]

**Given** a valid `parity.yaml` with one structure entry, a matching coverage file, and all source files have corresponding test files that pass all enforced rules
**When** the user runs `parity check`
**Then** the command outputs a table with `Y` in the OK column for every file, prints the coverage summary, and returns exit code 0.

### S001-AS-002 Violations found: exit code 1 [P1]

**Given** a valid `parity.yaml` with one structure entry and at least one source file that fails an enforced rule (e.g., missing test file or coverage below threshold)
**When** the user runs `parity check`
**Then** the command outputs a table with `N` in the OK column for the failing file(s), and returns exit code 1.

### S001-AS-003 JSON output mode [P1]

**Given** a valid project setup with both passing and failing files
**When** the user runs `parity check --format=json`
**Then** stdout contains exactly one JSON document matching the S001-IF-003 schema, with `passed: false` at the top level, individual file results in `structures[].files[]`, and no non-JSON text anywhere in stdout.

### S001-AS-004 Show tests flag with PHPUnit XML [P2]

**Given** a valid project with PHPUnit XML coverage data containing test method names
**When** the user runs `parity check --show-tests`
**Then** the table output includes individual test method names in the coverage attribution columns instead of a count, and the `--show-tests` boolean is passed to the rendering context.

### S001-AS-005 Custom config path [P1]

**Given** a `parity.yaml` file located at `/tmp/my-project/config/parity.yaml` with valid structure entries referencing source directories relative to `/tmp/my-project/config/`
**When** the user runs `parity check --config=/tmp/my-project/config/parity.yaml` from any directory
**Then** the command uses `/tmp/my-project/config/` as the project root, resolves all relative paths from that root, and produces correct output.

### S001-AS-006 Init creates config file [P1]

**Given** a directory with no `parity.yaml` file
**When** the user runs `parity init` from that directory
**Then** a `parity.yaml` file is created in the current directory containing the default template (with `settings`, `coverage_xml`, `min_coverage`, and `structure` sections), the command prints `Created parity.yaml.`, and returns exit code 0.

### S001-AS-007 Init with existing config [P1]

**Given** a directory that already contains a `parity.yaml` file
**When** the user runs `parity init`
**Then** the existing file is not modified, the command prints `parity.yaml already exists.` as a warning, and returns exit code 0.

### S001-AS-008 Missing config file [P1]

**Given** a directory without `parity.yaml` and no `--config` flag
**When** the user runs `parity check`
**Then** the command prints `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.` as an error and returns exit code 1.

### S001-AS-009 Missing coverage file [P1]

**Given** a valid `parity.yaml` but none of the configured `coverage_xml` paths exist as files or directories
**When** the user runs `parity check`
**Then** the command prints an error listing all tried paths: `No coverage file or directory found (tried: {paths}).` and returns exit code 1.

### S001-AS-010 Empty structure list [P1]

**Given** a valid `parity.yaml` with `structure: []` (empty array) and a valid coverage file
**When** the user runs `parity check`
**Then** the command prints a warning `No structure entries in parity.yaml.` and returns exit code 0 (no violations possible).

### S001-AS-011 Global coverage below threshold [P2]

**Given** a valid `parity.yaml` with `min_coverage_global: 80` and coverage data showing 65% global coverage, with all per-file rules passing
**When** the user runs `parity check`
**Then** the command prints `Global coverage 65% is below minimum 80%.` as an error, the summary table shows `FAIL` for Global coverage, and the command returns exit code 1.

### S001-AS-012 Multiple structures in one run [P1]

**Given** a valid `parity.yaml` with three structure entries (e.g., Unit Actions, Unit Services, Feature Tests)
**When** the user runs `parity check`
**Then** the command outputs one table per structure, each with its own title and source/test path subtitle, followed by a single combined summary section. Exit code reflects the aggregate result of all structures.
