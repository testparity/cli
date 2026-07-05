# S001 Interface Requirements

### Check Command Flag Contract

S001-IF-001 [P1] The `check` command **MUST** accept the following flags with exactly these semantics:

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--config=<path>` | string (optional) | `./parity.yaml` | Absolute or relative path to a `parity.yaml` config file. When provided, the project root is derived as the parent directory of this file. |
| `--format=<value>` | enum: `table`, `json` | `table` | Controls output format. `table` produces human-readable console tables. `json` produces a single JSON document. |
| `--show-tests` | boolean (flag) | `false` | When present, shows individual test method names instead of counts in the table. Only effective with PHPUnit XML coverage. |

S001-IF-001.a No positional arguments **MUST** be accepted.
S001-IF-001.b Unknown flags **MUST** be rejected by the Symfony Console framework with an appropriate error (this is default framework behavior and does not require custom handling).

### Exit Code Contract

S001-IF-002 [P1] The `check` command **MUST** use exactly two exit codes:

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `Command::SUCCESS` | All enforced rules pass, global coverage (if configured) meets threshold, config loaded successfully, coverage data loaded successfully. |
| `1` | `Command::FAILURE` | Any of: enforced rule failure, global coverage below threshold, config file missing/unreadable/invalid, coverage data missing. |

S001-IF-002.a The `init` command **MUST** use exactly two exit codes:

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `Command::SUCCESS` | Config file created, or config file already exists (idempotent). |
| `1` | `Command::FAILURE` | Cannot determine `getcwd()`, or file write fails. |

### JSON Output Schema

S001-IF-003 [P1] When `--format=json` is used, the output **MUST** conform to this schema:

```json
{
  "passed": boolean,
  "global_coverage": number | null,
  "min_coverage_global": number | null,
  "structures": [
    {
      "name": string,
      "source_path": string,
      "test_path": string,
      "files": [
        {
          "source": string,
          "test": string,
          "passed": boolean,
          "rules": {
            "<rule-name>": {
              "passed": boolean,
              "value": string | null,
              "error": string | null
            }
          }
        }
      ]
    }
  ]
}
```

S001-IF-003.a The `passed` field at the top level **MUST** be the logical AND of: all file-level `passed` values across all structures, and global coverage meeting threshold (when configured).
S001-IF-003.b The `rules` object keys **MUST** match the string returned by `RuleInterface::name()` for each evaluated rule.
S001-IF-003.c The `value` field **MUST** contain the `RuleResult::$value` property (typically a human-readable string like `"85%"` or `"CoversClass"`).
S001-IF-003.d The `error` field **MUST** contain the `RuleResult::$error` property when the rule fails; null when it passes.
S001-IF-003.e The `source` field in each file entry **MUST** be the full relative path from project root (e.g., `app/Services/Auth/LoginService.php`), not just the filename.
S001-IF-003.f The `test` field in each file entry **MUST** be the expected test relative path from project root (e.g., `tests/Unit/Services/Auth/LoginServiceTest.php`), regardless of whether the test file exists.
S001-IF-003.g The JSON **MUST** be encoded with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` flags.
S001-IF-003.h The JSON document **MUST** be the only content written to stdout. No warnings, titles, or summary text.

### Table Output Structure

S001-IF-004 [P1] In table mode, the output **MUST** follow this structure for each configured structure:

```
Structure: {name}
  Source: {source_path}
  Tests:  {test_path}

+--------+------+...+----+
| Source | Test |...| OK |
+--------+------+...+----+
| dir/   | dir/ |...| -  |   <-- gray directory separator
|  File  | Test |...| Y  |   <-- file row (green Y or red N)
+--------+------+...+----+
```

S001-IF-004.a The structure title **MUST** use Symfony Console `title()` formatting.
S001-IF-004.b Source and test paths **MUST** be displayed in gray text.
S001-IF-004.c Directory separator rows **MUST** display the directory path in gray with `/` suffix, and gray `-` for all rule and OK columns.
S001-IF-004.d File rows within a directory group **MUST** be indented with two leading spaces in the Source and Test columns.
S001-IF-004.e The OK column **MUST** show `<fg=green>Y</>` for passing files and `<fg=red>N</>` for failing files.
S001-IF-004.f After all structure tables, a `Summary` section **MUST** be rendered with a coverage summary table.

### Init Command Contract

S001-IF-005 [P1] The `init` command **MUST** accept no arguments and no flags.

S001-IF-005.a Output on success: `Created parity.yaml.` (info level).
S001-IF-005.b Output when file exists: `parity.yaml already exists.` (warning level).
S001-IF-005.c Output when `getcwd()` fails: `Could not determine current directory.` (error level).
S001-IF-005.d Output when write fails: `Could not write parity.yaml.` (error level).
S001-IF-005.e The created file **MUST** be named exactly `parity.yaml` (not `.yml`, not `.json`).
S001-IF-005.f The created file **MUST** be placed in the current working directory, not in any subdirectory.
