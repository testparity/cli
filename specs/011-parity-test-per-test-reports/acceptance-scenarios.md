# S011 Acceptance Scenarios

### S011-AS-001 Generate one report per expected test [P1]

**Given** a project with two discovered expected tests
**And** `test.command` runs each test file in isolation
**And** `test.coverage` points to a unique artifact path using `{slug}`
**When** `parity test` is executed
**Then** the output directory contains `index.json`
**And** the `reports` array in the manifest contains exactly two entries
**And** each entry points to one JSON report file under `reports/`

### S011-AS-002 Run `parity check` by default after generation [P1]

**Given** a project whose discovered tests generate valid Parity per-test reports
**When** `parity test --format=json` is executed
**Then** `parity check` is invoked automatically against the generated report directory
**And** the final stdout is valid `parity check` JSON output
**And** matched-coverage-compatible rule output is present

### S011-AS-003 `--no-check` skips follow-up analysis [P1]

**Given** a project whose discovered tests generate valid Parity per-test reports
**When** `parity test --no-check` is executed
**Then** the report directory is written successfully
**And** no delegated `parity check` output is emitted
**And** the command exits with code 0

### S011-AS-004 Missing `test.command` fails fast [P1]

**Given** a valid `parity.yaml` with no `test.command`
**When** `parity test` is executed
**Then** the command exits with code 1
**And** stderr contains `Missing test.command in parity.yaml`

### S011-AS-005 Missing `test.coverage` fails fast [P1]

**Given** a valid `parity.yaml` with no `test.coverage`
**When** `parity test` is executed
**Then** the command exits with code 1
**And** stderr contains `Missing test.coverage in parity.yaml`

### S011-AS-006 Output override changes report directory [P2]

**Given** `test.reports` is configured as `.parity/per-test`
**When** `parity test --output build/parity-attribution` is executed
**Then** the generated `index.json` is written under `build/parity-attribution`
**And** delegated `parity check` consumes that overridden directory first

### S011-AS-007 Non-attribution runner artifact is normalized [P2]

**Given** the isolated runner emits a Clover XML or Cobertura XML file for one test run
**When** `parity test` normalizes that artifact
**Then** the resulting per-test JSON report contains the covered lines for each source file
**And** the report is valid input for `parity check`

### S011-AS-008 Explicit file_map participates in discovery [P2]

**Given** a structure entry with `file_map` routing `Foo.php` to `Ownership/FooOwnershipTest.php`
**When** `parity test` discovers expected tests
**Then** `Ownership/FooOwnershipTest.php` is executed instead of convention-derived mapping
