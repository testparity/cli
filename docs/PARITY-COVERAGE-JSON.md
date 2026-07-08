# Parity Coverage JSON

Specs: S003

`parity-coverage.json` is a language-neutral attribution format for ecosystems whose native coverage output does not include which test covered which line.

Use it when native runner output cannot express which test covered which line, but you still want `matched-coverage` and `coverage-attribution`.

## Schema

```json
{
  "version": 1,
  "globalPercent": 80,
  "files": [
    {
      "path": "src/formatCurrency.ts",
      "coveragePercent": 70,
      "totalExecutableLines": 10,
      "lines": [
        {
          "line": 1,
          "coveredBy": ["formatCurrency.test::coversPrimaryBehavior"]
        }
      ]
    }
  ]
}
```

Fields:

| Field | Required | Description |
| --- | --- | --- |
| `version` | Recommended | Format version. Use `1`. |
| `globalPercent` | Optional | Project-level coverage percentage from `0` to `100`. |
| `files[].path` | Yes | Source path relative to the project root. |
| `files[].coveragePercent` | Recommended | All-test coverage percentage for the file. |
| `files[].totalExecutableLines` | Required for `matched-coverage` | Number of executable lines in the file. |
| `files[].lines[].line` | Yes | Executable line number. |
| `files[].lines[].coveredBy` | Yes | Test names that covered that line. |

## Configuration

Put `parity-coverage.json` near the front so Parity uses attribution data when available and falls back to portable aggregate formats otherwise.

```yaml
coverage_xml: [.parity/per-test, parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]

structure:
  - name: "Utilities"
    paths:
      source: "src"
      test: "tests"
    rules:
      - minimum-coverage:
          min: 70
      - matched-coverage:
          min: 40
```

## JavaScript Converter Example

This minimal converter shows the shape a custom tool should produce. Real converters should read test-run attribution from the runner, coverage library, or instrumentation layer available in the project.

```js
import { writeFileSync } from 'node:fs'

const report = {
  version: 1,
  globalPercent: 80,
  files: [
    {
      path: 'src/formatCurrency.ts',
      coveragePercent: 70,
      totalExecutableLines: 10,
      lines: [
        {
          line: 1,
          coveredBy: ['formatCurrency.test::formats_cents']
        },
        {
          line: 2,
          coveredBy: [
            'formatCurrency.test::formats_cents',
            'checkout.integration.test::renders_total'
          ]
        }
      ]
    }
  ]
}

writeFileSync('parity-coverage.json', `${JSON.stringify(report, null, 2)}\n`)
```

Then run:

```bash
parity check --config=parity.yaml --format=json
```

If you want Parity itself to generate attribution by running one expected test file at a time, configure `parity test` and let it write `.parity/per-test/`, then list that directory first in `coverage_xml`.

## Reading The Output

For a weak file, Parity can show:

```json
{
  "minimum-coverage": { "value": "70%" },
  "matched-coverage": { "value": "40%" },
  "coverage-attribution": { "value": "3|2" }
}
```

`3|2` means three tests covered at least one line in the file, and two of those tests were incidental non-matching tests.
