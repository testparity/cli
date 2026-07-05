# S001 Functional Requirements

### Check Command Signature

S001-FR-001 [P1] The CLI **MUST** expose a `check` command with the Artisan/Symfony signature:
```
check
  {--show-tests : Show test names that cover each file in the table (PHPUnit XML only; default is count only)}
  {--format=table : Output format: table (default) or json}
  {--config= : Path to parity.yaml (default: ./parity.yaml)}
```
S001-FR-001.a The command description **MUST** be: `Check structural parity and code coverage per file using pluggable rules; does not run tests`.
S001-FR-001.b All three options **MUST** be optional flags (no required arguments).

### Config File Resolution

S001-FR-002 [P1] When `--config` is not provided, the `check` command **MUST** resolve the project root by checking for `parity.yaml` in the current working directory.
S001-FR-002.a If `parity.yaml` exists in `getcwd()`, the project root **MUST** be set to `realpath(getcwd())`.
S001-FR-002.b If `parity.yaml` does not exist in `getcwd()` and `--config` is not provided, the command **MUST** print an error message and return exit code 1.
S001-FR-002.c The error message **MUST** be: `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.`

S001-FR-003 [P1] When `--config=<path>` is provided, the `check` command **MUST** resolve the project root as the directory containing the specified config file.
S001-FR-003.a The config path **MUST** be resolved via `realpath()`.
S001-FR-003.b If the specified config file does not exist (i.e., `realpath()` returns false or the resolved path is not a file), the command **MUST** print the S001-FR-002.c error message and return exit code 1.

### Format Flag

S001-FR-004 [P1] The `--format` flag **MUST** accept exactly two values: `table` (default) and `json`.
S001-FR-004.a When `--format=table` or when `--format` is omitted, the command **MUST** produce human-readable table output to stdout.
S001-FR-004.b When `--format=json`, the command **MUST** produce a single JSON document to stdout with no additional decorative output (no titles, warnings, or summary tables).

### Show Tests Flag

S001-FR-005 [P2] The `--show-tests` flag **MUST** be a boolean flag (no value) that, when present, causes the table output to display individual test method names covering each file instead of a count.
S001-FR-005.a This flag **MUST** only have an observable effect when PHPUnit XML coverage data is in use.
S001-FR-005.b When `--format=json` is also specified, the `--show-tests` flag **SHOULD** have no effect on JSON output structure (test data is always included in JSON when available).

### Exit Codes

S001-FR-006 [P1] The `check` command **MUST** return exit code 0 when all enforced rules pass for all files across all structures and global coverage (if configured) meets its threshold.
S001-FR-006.a The `check` command **MUST** return exit code 1 when any enforced rule fails for any file, or when global coverage falls below `min_coverage_global`.
S001-FR-006.b The `check` command **MUST** return exit code 1 when the config file cannot be loaded (missing, unreadable, or invalid YAML).
S001-FR-006.c The `check` command **MUST** return exit code 1 when no coverage file or directory can be found from the configured candidates.
S001-FR-006.d Exit code semantics **MUST** be identical regardless of `--format` (table or json).

### Table Output Format

S001-FR-007 [P1] In table mode, the command **MUST** render one table per structure entry in `parity.yaml`.
S001-FR-007.a Each table **MUST** be preceded by a title line showing the structure name.
S001-FR-007.b Below the title, the source path and test path **MUST** be displayed as gray-colored subtitle lines.
S001-FR-007.c Table headers **MUST** be dynamically generated from the active rules: `Source`, `Test`, then one column per rule that returns a non-null `columnHeader()`, then `OK`.
S001-FR-007.d The `CoverageAttributionRule` **MUST** produce two columns: its main column header plus an `Other` column.
S001-FR-007.e Rows within each table **MUST** be sorted alphabetically by relative source path.
S001-FR-007.f Rows **MUST** be visually grouped by subdirectory, with a gray separator row showing the directory path when the directory changes.
S001-FR-007.g The final `OK` column **MUST** display `Y` (green) when all enforced rules pass for that file, or `N` (red) when any enforced rule fails.

### JSON Output Format

S001-FR-008 [P1] In JSON mode, the command **MUST** output a single JSON object to stdout using `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`.
S001-FR-008.a The top-level object **MUST** contain exactly four keys: `passed` (boolean), `global_coverage` (number or null), `min_coverage_global` (number or null), and `structures` (array).
S001-FR-008.b Each entry in `structures` **MUST** contain: `name` (string), `source_path` (string), `test_path` (string), and `files` (array).
S001-FR-008.c Each entry in `files` **MUST** contain: `source` (string -- full relative path), `test` (string -- expected test relative path), `passed` (boolean), and `rules` (object).
S001-FR-008.d The `rules` object **MUST** be keyed by rule name, with each value containing `passed` (boolean), `value` (string or null), and `error` (string or null).
S001-FR-008.e The top-level `passed` field **MUST** be `false` if any file has a failing enforced rule or global coverage is below threshold; `true` otherwise.

### Structure Iteration and Rule Evaluation

S001-FR-009 [P1] The `check` command **MUST** iterate over every entry in the `structure` array of the loaded config.
S001-FR-009.a For each structure, the command **MUST** discover all files matching `settings.source_extension` under the resolved source directory.
S001-FR-009.b For each source file, the command **MUST** build a `RuleContext` and evaluate every resolved rule, collecting `RuleResult` objects.
S001-FR-009.c A file is considered failing if any rule where `isEnforced()` returns `true` produces a `RuleResult` with `passed === false`.

### Global Coverage Reporting

S001-FR-010 [P1] When `min_coverage_global` is configured and global coverage data is available, the `check` command **MUST** compare global coverage against the threshold.
S001-FR-010.a In table mode, if global coverage meets or exceeds the threshold, the command **MUST** display an info-level message: `Global coverage: {percent}% (minimum: {threshold}%).`
S001-FR-010.b In table mode, if global coverage is below the threshold, the command **MUST** display an error-level message: `Global coverage {percent}% is below minimum {threshold}%.`
S001-FR-010.c In JSON mode, global coverage data **MUST** be included only in the top-level `global_coverage` and `min_coverage_global` fields; no separate message is rendered.
S001-FR-010.d Global coverage below threshold **MUST** cause the overall result to be failure (exit code 1).

### Coverage Summary Table

S001-FR-011 [P1] In table mode, after all structure tables, the command **MUST** render a `Summary` section containing a four-column table: `Coverage`, `Value`, `Required`, `Status`.
S001-FR-011.a The summary table **MUST** include rows for: Global coverage, Per-file avg (all tests), Per-file min (all tests), and conditionally Per-file min (matching test only) when `min_matched_coverage` is configured.
S001-FR-011.b Status cells **MUST** display `OK` (green) when the value meets the requirement, `FAIL` (red) when below, or a gray dash when not applicable.

### Unmatched Test Warning

S001-FR-012 [P2] When PHPUnit XML coverage data is in use, the command **SHOULD** warn about tests found in coverage data that do not match any configured structure entry.
S001-FR-012.a The warning **MUST** be prefixed with: `Tests that did not match any structure (e.g. wrong path/namespace):`.
S001-FR-012.b Each unmatched test path **MUST** be listed on its own line, indented, in yellow.
S001-FR-012.c This warning **MUST** only appear in table mode, not in JSON mode.

### Init Command Signature

S001-FR-013 [P1] The CLI **MUST** expose an `init` command with the Artisan signature `init` (no arguments, no options).
S001-FR-013.a The command description **MUST** be: `Create a default parity.yaml in the current directory`.

### Init Creates Default Config

S001-FR-014 [P1] The `init` command **MUST** write a `parity.yaml` file in the current working directory containing the default template configuration.
S001-FR-014.a The default config **MUST** include at minimum: `settings` block (with `namespace_roots`, `source_extension`, `test_suffix`, `test_extension`, `namespace_separator`), `coverage_xml` candidates, `min_coverage` default, and one example `structure` entry.
S001-FR-014.b On successful creation, the command **MUST** print: `Created parity.yaml.` and return exit code 0.

### Init Idempotency Guard

S001-FR-015 [P1] If `parity.yaml` already exists in the current directory, the `init` command **MUST NOT** overwrite it.
S001-FR-015.a The command **MUST** print a warning: `parity.yaml already exists.` and return exit code 0 (not an error).

### Plugin Loading and Warnings

S001-FR-016 [P1] The `check` command **MUST** invoke `PluginLoader::loadAll()` to discover and register plugins before evaluating rules.
S001-FR-016.a In table mode, any plugin warnings **MUST** be printed to stderr/console as warning-level messages prefixed with `Plugin: `.
S001-FR-016.b In JSON mode, plugin warnings **MUST NOT** appear in stdout output.

### Coverage Data Loading

S001-FR-017 [P1] The `check` command **MUST** attempt to load coverage data from the candidate paths specified in `settings.coverage_paths` (derived from `coverage_xml` in config).
S001-FR-017.a The command **MUST** try candidates in order and use the first path that resolves to an existing file or directory.
S001-FR-017.b A directory candidate is valid if it contains an `index.xml` file (PHPUnit XML coverage format).
S001-FR-017.c A file candidate is valid if it exists as a regular file (Clover XML format).
S001-FR-017.d If no candidate resolves, the command **MUST** print an error listing all tried paths and return exit code 1.

### Legacy Config Format Support

S001-FR-018 [P2] The `check` command **SHOULD** support the legacy structure entry format alongside the new format.
S001-FR-018.a Legacy format uses `source_path`/`test_path` keys; new format uses `paths: { source: ..., test: ... }`.
S001-FR-018.b Legacy format uses `enforce_attribute`/`enforce_coverage_link`/`min_coverage` keys; new format uses `rules: [...]` array.
S001-FR-018.c When legacy keys are present and `rules` is absent, the command **MUST** translate them into the equivalent rules array internally.
