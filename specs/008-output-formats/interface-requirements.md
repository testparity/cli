# S008 Interface Requirements

### JSON Output Schema

S008-IF-001 [P1] When `--format=json` is used, the output **MUST** conform to this schema:

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

S008-IF-001.a The root object **MUST** contain exactly four keys: `passed`, `global_coverage`, `min_coverage_global`, `structures`. No additional keys are permitted at the root level.

S008-IF-001.b `passed` **MUST** be a JSON boolean. It is the logical conjunction of: (1) all file-level `passed` values across all structures, and (2) global coverage meeting threshold (when `min_coverage_global` is non-null).

S008-IF-001.c `global_coverage` **MUST** be a JSON number (float) representing the project-wide coverage percentage, or JSON `null` if global coverage data is not available.

S008-IF-001.d `min_coverage_global` **MUST** be a JSON number (float) representing the configured threshold, or JSON `null` if the threshold is not configured.

S008-IF-001.e `structures` **MUST** be a JSON array. It **MUST** contain one entry per `structure` block in `parity.yaml`, in the same order as they appear in config.

S008-IF-001.f Each structure entry **MUST** contain exactly four keys: `name` (string), `source_path` (string), `test_path` (string), `files` (array).

S008-IF-001.g `name` **MUST** match the `name` field from the structure's config entry (e.g., `"Unit Services"`).

S008-IF-001.h `source_path` **MUST** be the resolved source path string (e.g., `"app/Services"`).

S008-IF-001.i `test_path` **MUST** be the resolved test path string (e.g., `"tests/Unit/Services"`).

S008-IF-001.j `files` **MUST** be a JSON array containing one entry per source file discovered in that structure's source directory.

S008-IF-001.k Each file entry **MUST** contain exactly four keys: `source` (string), `test` (string), `passed` (boolean), `rules` (object).

S008-IF-001.l `source` **MUST** be the full relative path from the project root (e.g., `"app/Services/Auth/LoginService.php"`), not a basename or structure-relative path.

S008-IF-001.m `test` **MUST** be the expected test file path relative to the project root (e.g., `"tests/Unit/Services/Auth/LoginServiceTest.php"`), regardless of whether the test file exists on disk.

S008-IF-001.n `passed` at the file level **MUST** be `true` only when every enforced rule passes for that file; `false` when any enforced rule fails.

S008-IF-001.o `rules` **MUST** be a JSON object keyed by the string returned by `RuleInterface::name()` for each rule evaluated against that file (e.g., `"test-exists"`, `"minimum-coverage"`, `"coverage-attribution"`).

S008-IF-001.p Each rule value **MUST** contain exactly three keys: `passed` (boolean), `value` (string or null), `error` (string or null).

S008-IF-001.q `passed` in a rule entry **MUST** reflect `RuleResult::$passed`.

S008-IF-001.r `value` **MUST** reflect `RuleResult::$value`. Typical values include: `"Y"` / `"N"` for test-exists, `"85%"` for minimum-coverage, `"3|1"` for coverage-attribution.

S008-IF-001.s `error` **MUST** reflect `RuleResult::$error`. Non-null only when the rule fails. Example: `"Coverage 45% is below minimum 80%"`.

S008-IF-001.t The JSON **MUST** be encoded with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`.

S008-IF-001.u The JSON document **MUST** be the only content written to stdout. No ANSI codes, no warnings, no titles, no blank lines before/after.

### Table Column Contract

S008-IF-002 [P1] The table header row **MUST** follow this structure:

```
| Source | Test | {rule1_header} | {rule2_header} | ... | OK |
```

S008-IF-002.a `Source` and `Test` are always the first two columns.
S008-IF-002.b Rule columns appear in the order rules are resolved for the structure.
S008-IF-002.c Only rules with non-null `columnHeader()` produce columns.
S008-IF-002.d `CoverageAttributionRule` produces two adjacent columns: `#` and `Other`.
S008-IF-002.e `OK` is always the last column.

S008-IF-002.f File row cells **MUST** follow this mapping:

| Column | Cell Content |
|--------|-------------|
| Source | Basename of source file (indented with 2 spaces if in a subdirectory) |
| Test | Basename of expected test file (indented with 2 spaces if in a subdirectory) |
| Rule columns | Output of `RuleInterface::formatCell(RuleResult)` (ANSI-tagged) |
| CoverageAttribution "Other" | Output of `CoverageAttributionRule::formatOtherCell(RuleResult)` |
| OK | `<fg=green>Y</>` if all enforced rules pass; `<fg=red>N</>` otherwise |

S008-IF-002.g Directory separator rows **MUST** follow this mapping:

| Column | Cell Content |
|--------|-------------|
| Source | `<fg=gray>{dir}/</>`  |
| Test | `<fg=gray>{dir}/</>`  |
| Rule columns | `<fg=gray>-</>` each |
| OK | `<fg=gray>-</>` |

### Exit Code Contract

S008-IF-003 [P1] The `check` command **MUST** use exactly two exit codes for the output-relevant path:

| Code | Constant | Condition |
|------|----------|-----------|
| `0` | `Command::SUCCESS` | `$hasFailure === false` |
| `1` | `Command::FAILURE` | `$hasFailure === true` |

S008-IF-003.a `$hasFailure` is initialized to `true` if `global_coverage < min_coverage_global` (when both are non-null).
S008-IF-003.b `$hasFailure` is set to `true` if any file has `$allPassed === false` (i.e., any enforced rule failed).
S008-IF-003.c `$hasFailure` is never set to `true` by informational rule results.
S008-IF-003.d The same `$hasFailure` boolean drives both the `return` statement and the JSON `passed` field (inverted: `passed = !$hasFailure`).

### Coverage Summary Table Schema

S008-IF-004 [P1] The coverage summary table in table mode **MUST** have this structure:

```
| Coverage                         | Value   | Required | Status |
|----------------------------------|---------|----------|--------|
| Global                           | {n}%    | {m}%     | OK/FAIL|
| Per-file avg (all tests)         | {n}%    | --       | --     |
| Per-file min (all tests)         | {n}%    | {m}%     | OK/FAIL|
| Per-file min (matching test only)| --      | {m}%     | --     |  <-- only if min_matched_coverage set
```

S008-IF-004.a Values **MUST** be formatted with `sprintf('%.2f%%', $value)` for percentages.
S008-IF-004.b Required values **MUST** be formatted with `sprintf('%.0f%%', $value)` for thresholds.
S008-IF-004.c Missing or non-applicable values **MUST** display `--` (em dash character).
S008-IF-004.d Status cells **MUST** display `<fg=green>OK</>` when the value meets the requirement, `<fg=red>FAIL</>` when below, or `<fg=gray>--</>` when not applicable.

### Rule-to-Column Mapping Contract

S008-IF-005 [P1] Each built-in rule produces the following column behavior:

| Rule Name | `columnHeader()` | `isEnforced()` | Column Behavior |
|-----------|------------------|----------------|-----------------|
| `test-exists` | `"∃"` | `true` | Green `Y` / Red `N` |
| `enforce-coverage-link` | varies | `true` | Green pass label / Red fail label |
| `minimum-coverage` | `"Cov"` | `true` | Green `{n}%` / Red `{n}%` |
| `matched-coverage` | varies | `true` | Green `{n}%` / Red `{n}%` |
| `coverage-attribution` | `"#"` | `false` | Count (main) + Yellow/Gray count (Other) |

S008-IF-005.a Plugin rules that implement `RuleInterface` **MUST** integrate identically: if `columnHeader()` is non-null, they get a column; their `formatCell()` controls rendering; `isEnforced()` determines OK column participation.
S008-IF-005.b In JSON output, all evaluated rules appear in the `rules` object regardless of their `columnHeader()` return value.
