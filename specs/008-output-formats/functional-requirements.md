# S008 Functional Requirements

### Table Format as Default

S008-FR-001 [P1] When `--format` is omitted or set to `table`, the `check` command **MUST** produce human-readable table output to stdout.
S008-FR-001.a One table **MUST** be rendered per structure entry in `parity.yaml`.
S008-FR-001.b Each table **MUST** use the Symfony Console `table()` helper for rendering.
S008-FR-001.c After all structure tables, a coverage summary section **MUST** be rendered (see S008-FR-008).

### Dynamic Column Generation

S008-FR-002 [P1] Table column headers **MUST** be dynamically generated from the set of resolved rules for each structure.
S008-FR-002.a The first two columns **MUST** always be `Source` and `Test`, in that order.
S008-FR-002.b For each resolved rule where `RuleInterface::columnHeader()` returns a non-null string, one column **MUST** be appended using that header string.
S008-FR-002.c The `CoverageAttributionRule` **MUST** produce two columns: its `columnHeader()` value (`#`) and an additional `Other` column (see S008-FR-023).
S008-FR-002.d The final column **MUST** always be `OK`.
S008-FR-002.e Rules where `columnHeader()` returns `null` **MUST NOT** produce any table column.

### Directory Grouping

S008-FR-003 [P1] File rows in each table **MUST** be visually grouped by their parent directory within the structure's source path.
S008-FR-003.a When the directory of a file differs from the previous file's directory, a gray separator row **MUST** be inserted before that file's row.
S008-FR-003.b The separator row **MUST** display the directory path with a trailing `/` in gray in both the `Source` and `Test` columns.
S008-FR-003.c All rule columns and the `OK` column in the separator row **MUST** display a gray `-`.
S008-FR-003.d Files in the root of the source directory (directory is `.`) **MUST NOT** have a separator row inserted before them.
S008-FR-003.e File rows within a non-root directory group **MUST** have their `Source` and `Test` cell values indented with two leading spaces, showing only the basename.

### Alphabetical Sort

S008-FR-004 [P1] File rows within each structure table **MUST** be sorted alphabetically by the file's relative source path (using `strcmp` ordering).

### Pass/Fail Color Coding

S008-FR-005 [P1] Each rule **MUST** control its own cell formatting via `RuleInterface::formatCell(RuleResult)`.
S008-FR-005.a The `TestExistsRule` **MUST** render `<fg=green>Y</>` for pass and `<fg=red>N</>` for fail.
S008-FR-005.b The `MinimumCoverageRule` **MUST** render the coverage percentage in green when passing and red when failing.
S008-FR-005.c The `CoverageAttributionRule` **MUST** render the total test count for its main column and the non-matching test count (yellow, or gray `-` for zero) for its `Other` column.
S008-FR-005.d When a rule's result value is null or indicates no data (e.g., `0|0` for coverage-attribution), the cell **SHOULD** display a gray `-`.

### OK Column Aggregation

S008-FR-006 [P1] The `OK` column for each file row **MUST** display `<fg=green>Y</>` when all enforced rules pass for that file, and `<fg=red>N</>` when any enforced rule fails.
S008-FR-006.a Only rules where `RuleInterface::isEnforced()` returns `true` **MUST** be considered when computing the OK value.
S008-FR-006.b Informational rules (where `isEnforced()` returns `false`) **MUST NOT** influence the OK column, regardless of their `RuleResult::$passed` value.

### Structure Title and Path Subtitles

S008-FR-007 [P1] In table mode, each structure **MUST** be preceded by a title and path information.
S008-FR-007.a The title **MUST** be rendered using Symfony Console `title()` with the format: `Structure: {name}`.
S008-FR-007.b Below the title, the source path **MUST** be displayed as `  Source: {source_path}` in gray text.
S008-FR-007.c Below the source line, the test path **MUST** be displayed as `  Tests:  {test_path}` in gray text.

### Coverage Summary Table

S008-FR-008 [P1] After all structure tables, the command **MUST** render a `Summary` section in table mode.
S008-FR-008.a The summary **MUST** use a Symfony Console `title('Summary')` heading.
S008-FR-008.b The summary **MUST** contain a four-column table with headers: `Coverage`, `Value`, `Required`, `Status`.
S008-FR-008.c Row 1 **MUST** show `Global` coverage: actual value (formatted as `{n}%` or `--` if null), required value (`{n}%` or `--`), and status (`OK` green / `FAIL` red / `--` gray).
S008-FR-008.d Row 2 **MUST** show `Per-file avg (all tests)`: the arithmetic mean of all per-file coverage values, with `--` for Required and Status.
S008-FR-008.e Row 3 **MUST** show `Per-file min (all tests)`: the minimum per-file coverage value, compared against the default `min_coverage` setting.
S008-FR-008.f Row 4 **MUST** be included only when `min_matched_coverage` is configured, showing `Per-file min (matching test only)`.
S008-FR-008.g The summary table **MUST NOT** appear in JSON mode.

### JSON Format Selection

S008-FR-009 [P1] When `--format=json` is specified, the `check` command **MUST** produce a single JSON document to stdout instead of table output.
S008-FR-009.a No table rendering, title lines, subtitle lines, summary tables, warnings, or info messages **MUST** be written to stdout in JSON mode.
S008-FR-009.b The JSON document **MUST** be the sole content on stdout.

### JSON Top-Level Schema

S008-FR-010 [P1] The JSON output **MUST** be a single object with exactly four top-level keys.
S008-FR-010.a `passed` (boolean): **MUST** be `true` when all enforced rules pass across all structures and global coverage (if configured) meets its threshold; `false` otherwise.
S008-FR-010.b `global_coverage` (number or null): **MUST** contain the global coverage percentage as a float, or `null` if global coverage data is not available.
S008-FR-010.c `min_coverage_global` (number or null): **MUST** contain the configured `min_coverage_global` threshold, or `null` if not configured.
S008-FR-010.d `structures` (array): **MUST** contain one entry per structure in `parity.yaml`, in config order.

### JSON Per-File Rule Results

S008-FR-011 [P1] Each entry in `structures` **MUST** contain `name` (string), `source_path` (string), `test_path` (string), and `files` (array).
S008-FR-011.a Each entry in `files` **MUST** contain `source` (string), `test` (string), `passed` (boolean), and `rules` (object).
S008-FR-011.b The `source` field **MUST** be the full relative path from the project root (e.g., `app/Services/Auth/LoginService.php`).
S008-FR-011.c The `test` field **MUST** be the expected test file path relative to the project root, regardless of whether the test file actually exists.
S008-FR-011.d The `passed` field **MUST** be `true` only when all enforced rules pass for that file; `false` if any enforced rule fails.
S008-FR-011.e The `rules` object **MUST** be keyed by the string returned by `RuleInterface::name()` for each evaluated rule.
S008-FR-011.f Each rule entry **MUST** contain `passed` (boolean), `value` (string or null), and `error` (string or null).
S008-FR-011.g The `value` field **MUST** contain `RuleResult::$value` (e.g., `"85%"`, `"Y"`, `"3|1"`).
S008-FR-011.h The `error` field **MUST** contain `RuleResult::$error` when the rule fails; `null` when it passes or is skipped.

### JSON Encoding Flags

S008-FR-012 [P1] The JSON document **MUST** be encoded using `json_encode()` with the flags `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`.
S008-FR-012.a Forward slashes in file paths **MUST NOT** be escaped (i.e., `app/Services` not `app\/Services`).
S008-FR-012.b The output **MUST** be formatted with indentation for human readability while remaining machine-parseable.

### JSON Output Purity

S008-FR-013 [P1] In JSON mode, stdout **MUST** contain only the JSON document.
S008-FR-013.a Plugin warnings **MUST NOT** appear in stdout (see S008-FR-026).
S008-FR-013.b Global coverage info/error messages **MUST NOT** appear in stdout.
S008-FR-013.c Structure titles and path subtitles **MUST NOT** appear in stdout.
S008-FR-013.d No ANSI escape sequences **MUST** appear in the JSON output.
S008-FR-013.e No leading or trailing whitespace beyond the JSON document's own formatting **MUST** appear in stdout (specifically, no blank lines before or after the JSON).

### Exit Code: All Pass

S008-FR-014 [P1] The `check` command **MUST** return exit code `0` (`Command::SUCCESS`) when:
S008-FR-014.a All enforced rules pass for all files across all structures, AND
S008-FR-014.b Global coverage meets or exceeds `min_coverage_global` (when configured), AND
S008-FR-014.c No fatal errors occurred during execution (config loaded, coverage data loaded).

### Exit Code: Enforced Violation

S008-FR-015 [P1] The `check` command **MUST** return exit code `1` (`Command::FAILURE`) when:
S008-FR-015.a Any file in any structure has a failing enforced rule (`RuleInterface::isEnforced() === true` and `RuleResult::$passed === false`), OR
S008-FR-015.b Global coverage is below `min_coverage_global` (when both the threshold is configured and global coverage data is available).

### Exit Code: Fatal Error

S008-FR-016 [P1] The `check` command **MUST** return exit code `1` (`Command::FAILURE`) when a fatal error prevents analysis:
S008-FR-016.a Config file not found, unreadable, or invalid YAML.
S008-FR-016.b No coverage file or directory found from configured candidates.

### Exit Code Parity Across Formats

S008-FR-017 [P1] The exit code semantics **MUST** be identical regardless of the `--format` flag value.
S008-FR-017.a The same set of evaluated results **MUST** produce the same exit code whether output is table or JSON.
S008-FR-017.b The `$hasFailure` boolean that drives the exit code **MUST** be computed before the format-specific rendering branch.

### Informational Rules Excluded from Exit Code

S008-FR-018 [P1] Rules where `RuleInterface::isEnforced()` returns `false` **MUST NOT** influence the exit code.
S008-FR-018.a The `coverage-attribution` rule (which returns `isEnforced() === false`) **MUST** never cause exit code 1, regardless of its result value.
S008-FR-018.b Informational rules **MUST** still appear in both table and JSON output; their exclusion applies only to exit code computation.

### ANSI Color in Table Mode

S008-FR-019 [P1] In table mode, the command **MUST** use Symfony Console ANSI color tags for visual indicators.
S008-FR-019.a Pass indicators **MUST** use `<fg=green>` (e.g., `<fg=green>Y</>`, `<fg=green>85%</>`).
S008-FR-019.b Fail indicators **MUST** use `<fg=red>` (e.g., `<fg=red>N</>`, `<fg=red>45%</>`).
S008-FR-019.c Informational/neutral indicators **MUST** use `<fg=gray>` (e.g., directory separators, dashes, non-applicable values).
S008-FR-019.d Warning indicators **MUST** use `<fg=yellow>` (e.g., unmatched test paths, non-zero "Other" counts).

### TTY Detection

S008-FR-020 [P2] Symfony Console's built-in output decoration handling **SHOULD** automatically suppress ANSI escape codes when stdout is not a TTY (e.g., piped to a file or another process).
S008-FR-020.a The command **SHOULD NOT** implement custom TTY detection; it **SHOULD** rely on the `OutputInterface::isDecorated()` mechanism provided by Symfony Console.
S008-FR-020.b When the `--no-ansi` flag is passed (provided by Symfony Console globally), all ANSI codes **MUST** be stripped from output.

### No ANSI Color in JSON Mode

S008-FR-021 [P1] In JSON mode, no ANSI escape sequences **MUST** appear in the output.
S008-FR-021.a The JSON document is produced via `json_encode()` of plain data arrays that never contain ANSI tags.
S008-FR-021.b The `RuleResult::$value` and `RuleResult::$error` fields **MUST** contain plain text, not ANSI-tagged strings. ANSI tags are applied only during table cell formatting (via `formatCell()`), not during result construction.

### Show Tests Flag

S008-FR-022 [P2] When `--show-tests` is present and attribution-capable coverage data is in use, the table output **SHOULD** display individual test method names instead of test counts in the coverage attribution column.
S008-FR-022.a The `--show-tests` flag **MUST** only affect table mode rendering. JSON output always includes test data when available, regardless of this flag.
S008-FR-022.b When coverage data does not include attribution (e.g., Clover XML or Cobertura XML), the `--show-tests` flag **MUST** have no observable effect.

### CoverageAttributionRule Dual-Column Rendering

S008-FR-023 [P1] The `CoverageAttributionRule` **MUST** produce two table columns, handled as a special case in the table rendering logic.
S008-FR-023.a The first column (header: `#`) **MUST** display the total number of covering tests, rendered by `formatCell()`.
S008-FR-023.b The second column (header: `Other`) **MUST** display the count of non-matching (other) tests, rendered by `formatOtherCell()`.
S008-FR-023.c When the "Other" count is zero, the cell **MUST** display a gray `-`.
S008-FR-023.d When the "Other" count is non-zero, the cell **MUST** display the count in yellow.
S008-FR-023.e In JSON mode, the `CoverageAttributionRule` **MUST** produce a single `coverage-attribution` key in the `rules` object with `value` containing the `"{total}|{other}"` encoded string.

### Global Coverage Message

S008-FR-024 [P2] In table mode, when `min_coverage_global` is configured and global coverage data is available, a message **MUST** be displayed before the structure tables.
S008-FR-024.a If global coverage >= threshold: info-level message `Global coverage: {percent}% (minimum: {threshold}%).`
S008-FR-024.b If global coverage < threshold: error-level message `Global coverage {percent}% is below minimum {threshold}%.`
S008-FR-024.c In JSON mode, global coverage **MUST** be conveyed only via the `global_coverage` and `min_coverage_global` top-level fields; no text message is rendered.

### Unmatched Test Warnings

S008-FR-025 [P2] In table mode with attribution-capable coverage data, the command **SHOULD** warn about tests found in coverage data that do not match any configured structure entry.
S008-FR-025.a The warning **MUST** be prefixed with: `Tests that did not match any structure (e.g. wrong path/namespace):`.
S008-FR-025.b Each unmatched test path **MUST** be listed indented with two spaces, in yellow.
S008-FR-025.c This warning **MUST NOT** appear in JSON mode.
S008-FR-025.d This warning **MUST NOT** affect the exit code.

### Plugin Warnings in JSON Mode

S008-FR-026 [P1] In JSON mode, plugin warnings **MUST NOT** be written to stdout.
S008-FR-026.a The `$isJson` check **MUST** gate the plugin warning loop so that no `$this->warn()` calls execute when format is `json`.
S008-FR-026.b In table mode, plugin warnings **MUST** be rendered as warning-level console messages prefixed with `Plugin: `.
