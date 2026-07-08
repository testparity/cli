# S011 Functional Requirements

### Command Availability

S011-FR-001 [P1] The CLI **MUST** expose a `parity test` command that generates Parity per-test coverage reports and, unless disabled, runs `parity check` against those generated reports.

### Required Test Configuration

S011-FR-002 [P1] The `parity test` command **MUST** read an optional top-level `test` configuration block from `parity.yaml`.
S011-FR-002.a The `test.command` key **MUST** be required for execution.
S011-FR-002.b The `test.coverage` key **MUST** be required for execution.
S011-FR-002.c The `test.reports` key **MAY** be omitted; when omitted, the default output directory **MUST** be `.parity/per-test`.

### Expected-Test Discovery

S011-FR-003 [P1] `parity test` **MUST** discover expected test files from the configured `structure` entries using the same namespace, suffix, extension, and path-mapping rules that `parity check` uses for ownership evaluation.
S011-FR-003.a Discovery **MUST** honor `settings.namespace_roots`, `settings.source_extension`, `settings.test_suffix`, `settings.test_extension`, and `settings.namespace_separator`.
S011-FR-003.b Discovery **MUST** honor explicit `file_map` overrides when present.
S011-FR-003.c Only discovered test files that exist on disk **MUST** be included in the execution set.
S011-FR-003.d The final execution set **MUST** be sorted deterministically by relative test path.

### Per-Test Command Execution

S011-FR-004 [P1] `parity test` **MUST** execute one runner process per discovered test file.
S011-FR-004.a The process working directory **MUST** be the resolved project root.
S011-FR-004.b The command line **MUST** be produced by placeholder expansion of `test.command`.
S011-FR-004.c Before execution, any existing target coverage artifact at the resolved `test.coverage` path **MUST** be removed.
S011-FR-004.d The parent directory for the resolved coverage target **MUST** be created before execution.

### Coverage Artifact Normalization

S011-FR-005 [P1] After each successful runner process, Parity **MUST** normalize the produced single-test coverage artifact into Parity's per-test JSON report shape.
S011-FR-005.a Normalization **MUST** preserve the discovered test identifier as the owning `test` value in the report.
S011-FR-005.b Normalization **MUST** preserve, per source file, the total executable line count and the set of covered executable lines.
S011-FR-005.c Normalization **MUST** support Parity JSON, PHPUnit XML directory output, Clover XML, and Cobertura XML as input artifact formats.

### Per-Test Report Directory Creation

S011-FR-006 [P1] `parity test` **MUST** create a Parity per-test report directory containing:
- `index.json`
- `reports/*.json`

S011-FR-006.a If the target report directory already exists, it **MUST** be removed before writing the new report set.
S011-FR-006.b The `reports/` subdirectory **MUST** be created automatically.
S011-FR-006.c Each generated report filename **MUST** be derived from a deterministic slug of the relative test path.

### Manifest Generation

S011-FR-007 [P1] `parity test` **MUST** write a manifest file at `{reports}/index.json`.
S011-FR-007.a The manifest **MUST** declare `version: 1`.
S011-FR-007.b The manifest **MUST** declare `kind: "parity-per-test-coverage"`.
S011-FR-007.c The manifest **MUST** contain one `reports` entry per generated test report with the normalized test identifier and report-relative path.

### Automatic Check Handoff

S011-FR-008 [P1] Unless `--no-check` is provided, `parity test` **MUST** invoke `parity check` after report generation.
S011-FR-008.a The generated report directory **MUST** be prepended to the effective `coverage_xml` candidate list for the delegated check run.
S011-FR-008.b The delegated check run **MUST** use a temporary config file rooted at the project root so that relative structure and coverage paths resolve identically to the original config.
S011-FR-008.c The delegated check run **MUST** forward `--format` and `--show-tests` when those options are supplied to `parity test`.
S011-FR-008.d The final exit code of `parity test` **MUST** equal the exit code returned by the delegated `parity check` run.

### No-Check Mode

S011-FR-009 [P2] When `--no-check` is supplied, `parity test` **MUST** stop after writing the Parity per-test report directory.
S011-FR-009.a In `--no-check` mode, the command **MUST** print a success message naming the report directory.
S011-FR-009.b In `--no-check` mode, no temporary check config file **MUST** be created.

### Output Directory Override

S011-FR-010 [P2] The `--output` option **MUST** override `test.reports` when provided.
S011-FR-010.a Relative output paths **MUST** resolve from the project root.
S011-FR-010.b Absolute output paths **MUST** be used as-is.

### Placeholder Expansion

S011-FR-011 [P2] The `test.command` and `test.coverage` templates **MUST** support the placeholders `{slug}`, `{test}`, `{test_abs}`, `{coverage}`, and `{project_root}`.
S011-FR-011.a Placeholders used in shell command execution **MUST** be shell-escaped.
S011-FR-011.b Placeholders used for non-shell path templating **MUST** be inserted without shell quoting.

### Missing Required Config Errors

S011-FR-012 [P1] If `test.command` or `test.coverage` is missing or blank, `parity test` **MUST** fail fast with exit code 1 and a configuration error message naming the missing key.

### Failed Test Process Handling

S011-FR-013 [P1] If any discovered test process exits non-zero, `parity test` **MUST** fail immediately.
S011-FR-013.a The command **MUST** print `Failed running test [{relative-test-path}]`.
S011-FR-013.b Any non-empty stderr from the runner **MUST** be printed.
S011-FR-013.c Any non-empty stdout from the runner **MUST** be printed.
S011-FR-013.d No subsequent test files **MUST** be executed after the first runner failure.

### No-Discovered-Tests Behavior

S011-FR-014 [P2] If no expected tests are discovered from the configured structures, `parity test` **MUST** print a warning and return exit code 0 without creating a report directory.

### Compatibility with `parity check`

S011-FR-015 [P1] The generated Parity per-test report directory **MUST** be directly consumable by `parity check` as an attribution-capable coverage source.
S011-FR-015.a `matched-coverage`, `coverage-attribution`, and `--show-tests` **MUST** work when `parity check` reads the generated directory.
