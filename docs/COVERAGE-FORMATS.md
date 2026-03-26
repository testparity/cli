# Coverage formats and per-test data

PHPUnit/Pest produce several coverage report formats. Only one stores **which test covered which line**.

## Supported by parity

**Parity supports Clover XML and PHPUnit XML.**

- **Clover XML** (PHPUnit/Pest `--coverage-clover=<file>`): single file; per-file % and global %. List in `parity.yaml` under `coverage_xml` (e.g. `clover.xml` or `coverage.xml`).
- **PHPUnit XML** (PHPUnit/Pest `--coverage-xml=<dir>`): directory with `index.xml` and per-file XML. Parity uses it for per-file %, global %, and **which tests cover each file** (shown in the "Covered by" column).

Use `coverage_xml` as a string or array; parity uses the first existing file or directory (with `index.xml`). Other formats (Cobertura, Crap4j, PHP, Text, HTML) are not read.

## Per-test coverage (which test did which coverage)

| Format | Option | Parity supports? | Per-test data? | Notes |
|--------|--------|------------------|----------------|--------|
| **Clover** | `--coverage-clover=<file>` | **Yes** | No | Single XML file; per-line **count** only (no test names). Parity uses this for per-file % and global %. |
| **PHPUnit XML** | `--coverage-xml=<dir>` | **Yes** | **Yes** | Directory with `index.xml` and per-file XML. Parity shows per-file %, global %, and "Covered by" test names. |
| Cobertura | `--coverage-cobertura=<file>` | No | No | Per-file/line metrics only. |
| Crap4j | `--coverage-crap4j=<file>` | No | No | CRAP metrics only. |
| PHP | `--coverage-php=<file>` | No | No | Serialized PHP; not per-test. |
| Text | `--coverage-text` | No | No | Console summary. |
| HTML | `--coverage-html=<dir>` | No | Yes (UI only) | Rendered HTML shows "covered by" in popovers; not machine-readable for parity. |

## For parity

- **Clover**: Parity reads per-file coverage % and project-level % from Clover. No "which test covered this file".
- **PHPUnit XML**: If you point `coverage_xml` at a directory that contains `index.xml`, parity uses `PhpUnitXmlCoverageReader` and shows per-file %, global %, and a **"Covered by"** column with the test names that cover each source file (truncated to a few names + count).

To get per-test coverage in the table, generate PHPUnit XML and list it first in `coverage_xml`:

```yaml
coverage_xml: [coverage-xml, clover.xml]
```

Then run:

```bash
./vendor/bin/pest --coverage-clover=clover.xml --coverage-xml=coverage-xml
```

Parity will use the `coverage-xml/` directory when present and show the "Covered by" column.
