# S011 Interface Requirements

### `parity test` CLI Signature

S011-IF-001 [P1] The `parity test` command **MUST** conform to the following public CLI contract:

```text
parity test
  [--config=PATH]
  [--format=table|json]
  [--output=PATH]
  [--show-tests]
  [--no-check]
```

S011-IF-001.a `--config` **MUST** point to a `parity.yaml` file; when omitted, the command **MUST** look for `./parity.yaml` in the current working directory.
S011-IF-001.b `--format` **MUST** control the delegated `parity check` output format and default to `table`.
S011-IF-001.c `--output` **MUST** select the Parity per-test report directory.
S011-IF-001.d `--show-tests` **MUST** be forwarded to delegated `parity check`.
S011-IF-001.e `--no-check` **MUST** suppress delegated `parity check`.

### `test` Config Block Schema

S011-IF-002 [P1] The top-level `test` block in `parity.yaml` **MUST** support the following schema:

```yaml
test:
  command: <string>   # Required. Runner template executed once per discovered test file.
  coverage: <string>  # Required. Coverage artifact path template for that single test run.
  reports: <string>   # Optional. Default: ".parity/per-test"
```

S011-IF-002.a `command` **MUST** be a non-empty string.
S011-IF-002.b `coverage` **MUST** be a non-empty string.
S011-IF-002.c `reports` **MAY** be omitted.

### Placeholder Contract

S011-IF-003 [P1] The following placeholders **MUST** be supported inside `test.command` and `test.coverage` templates:

| Placeholder | Meaning |
|-------------|---------|
| `{slug}` | Deterministic slug for the discovered relative test path |
| `{test}` | Discovered test path relative to the project root |
| `{test_abs}` | Absolute path to the discovered test file |
| `{coverage}` | Absolute resolved path for the single-test coverage artifact |
| `{project_root}` | Absolute path to the project root |

S011-IF-003.a `{coverage}` **MUST** reflect the final resolved path after placeholder substitution inside `test.coverage`.
S011-IF-003.b `{slug}` **MUST** be stable for the same relative test path across repeated runs.

### Per-Test Report Manifest Schema

S011-IF-004 [P1] The Parity per-test report manifest at `index.json` **MUST** conform to the following schema:

```json
{
  "version": 1,
  "kind": "parity-per-test-coverage",
  "reports": [
    {
      "test": "Tests\\Unit\\FooTest",
      "path": "reports/0123456789abcdef.json"
    }
  ]
}
```

S011-IF-004.a `version` **MUST** be the integer `1`.
S011-IF-004.b `kind` **MUST** be the exact string `"parity-per-test-coverage"`.
S011-IF-004.c `reports` **MUST** be an array of objects.
S011-IF-004.d Each report entry **MUST** contain `test` and `path` string keys.
S011-IF-004.e Each `path` value **MUST** be relative to the manifest directory.

### Per-Test Report File Schema

S011-IF-005 [P1] Each JSON report file referenced by the manifest **MUST** conform to the following schema:

```json
{
  "version": 1,
  "test": "Tests\\Unit\\FooTest",
  "files": [
    {
      "path": "src/Foo.php",
      "totalExecutableLines": 12,
      "coveredLines": [4, 5, 8]
    }
  ]
}
```

S011-IF-005.a `version` **MUST** be the integer `1`.
S011-IF-005.b `test` **MUST** contain the normalized discovered test identifier.
S011-IF-005.c `files` **MUST** be an array of file entries sorted by path.
S011-IF-005.d Each file entry **MUST** contain:
- `path`: project-relative forward-slashed source path
- `totalExecutableLines`: integer greater than or equal to 0
- `coveredLines`: deduplicated ascending list of 1-based executable line numbers covered by this test

### Normalizer Input Formats

S011-IF-006 [P1] The artifact normalizer invoked by `parity test` **MUST** accept the following single-test coverage artifact inputs:

- A PHPUnit XML coverage directory containing `index.xml`
- A Parity JSON file with line attribution
- A Clover XML file
- A Cobertura XML file

S011-IF-006.a For aggregate formats such as Clover XML and Cobertura XML, the normalizer **MUST** attribute every covered line in the artifact to the current discovered test identifier because the artifact was produced from a single isolated test run.
