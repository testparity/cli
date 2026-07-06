# Coverage formats and per-test data

Specs: S003, S004, S010

PHPUnit/Pest produce several coverage report formats. Only one stores **which test covered which line**.

## Supported by parity

**Parity supports Parity JSON, Clover XML, Cobertura XML, and PHPUnit XML.**

- **Parity JSON** (`parity-coverage.json`): language-neutral single file; per-file %, global %, executable line counts, and which tests covered each line.
- **Clover XML** (PHPUnit/Pest `--coverage-clover=<file>`): single file; per-file % and global %. List in `parity.yaml` under `coverage_xml` (e.g. `clover.xml` or `coverage.xml`).
- **Cobertura XML** (many JS, Rust, Python, Go, and CI coverage tools): single file; per-file % and global %. Useful as a portable cross-language fallback.
- **PHPUnit XML** (PHPUnit/Pest `--coverage-xml=<dir>`): directory with `index.xml` and per-file XML. Parity uses it for per-file %, global %, and **which tests cover each file** (shown in the "Covered by" column).

Use `coverage_xml` as a string or array; parity uses the first existing file or directory (with `index.xml`). Other formats (Crap4j, PHP, Text, HTML) are not read.

## Per-test coverage (which test did which coverage)

| Format | Option | Parity supports? | Per-test data? | Notes |
|--------|--------|------------------|----------------|--------|
| **Parity JSON** | `parity-coverage.json` | **Yes** | **Yes** | Language-neutral file for custom converters and non-PHP ecosystems. |
| **Clover** | `--coverage-clover=<file>` | **Yes** | No | Single XML file; per-line **count** only (no test names). Parity uses this for per-file % and global %. |
| **PHPUnit XML** | `--coverage-xml=<dir>` | **Yes** | **Yes** | Directory with `index.xml` and per-file XML. Parity shows per-file %, global %, and "Covered by" test names. |
| **Cobertura** | Tool-specific, often `cobertura.xml` | **Yes** | No | Portable per-file/line metrics across many ecosystems; no test names. |
| Crap4j | `--coverage-crap4j=<file>` | No | No | CRAP metrics only. |
| PHP | `--coverage-php=<file>` | No | No | Serialized PHP; not per-test. |
| Text | `--coverage-text` | No | No | Console summary. |
| HTML | `--coverage-html=<dir>` | No | Yes (UI only) | Rendered HTML shows "covered by" in popovers; not machine-readable for parity. |

## For parity

- **Clover**: Parity reads per-file coverage % and project-level % from Clover. No "which test covered this file".
- **Cobertura**: Parity reads per-file coverage % and project-level % from Cobertura. No "which test covered this file".
- **Parity JSON**: If you point `coverage_xml` at `parity-coverage.json`, parity reads language-neutral per-line test attribution for `matched-coverage` and `coverage-attribution`.
- **PHPUnit XML**: If you point `coverage_xml` at a directory that contains `index.xml`, parity uses `PhpUnitXmlCoverageReader` and shows per-file %, global %, and a **"Covered by"** column with the test names that cover each source file (truncated to a few names + count).

To get per-test coverage in the table, generate PHPUnit XML and list it first in `coverage_xml`:

```yaml
coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
```

Minimal Parity JSON example:

```json
{
  "version": 1,
  "globalPercent": 80,
  "files": [
    {
      "path": "src/Foo.ts",
      "coveragePercent": 70,
      "totalExecutableLines": 10,
      "lines": [
        {
          "line": 1,
          "coveredBy": ["FooTest::coversPrimaryBehavior"]
        }
      ]
    }
  ]
}
```

See `docs/PARITY-COVERAGE-JSON.md` for the full schema and a JavaScript converter example.

Then run:

```bash
./vendor/bin/pest --coverage-clover=clover.xml --coverage-xml=coverage-xml
```

Parity will use the `coverage-xml/` directory when present and show the "Covered by" column.
