# Configuration Reference

Specs: S006, S007

Parity reads `parity.yaml` from the project root, or from the path passed with `--config`.

For all runtime defaults, legacy keys, and path-mapping behavior, see `docs/REFERENCE.md`.

```yaml
settings:
  namespace_roots:
    app: App
    tests: Tests
  source_extension: ".php"
  test_suffix: "Test"
  test_extension: ".php"
  namespace_separator: "\\"

coverage_xml: [.parity/per-test, parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
min_coverage: 80
min_coverage_global: 80

structure:
  - name: "Unit Services"
    paths:
      source: "app/Services"
      test: "tests/Unit/Services"
    rules:
      - enforce-coverage-link
      - minimum-coverage:
          min: 80
```

## Multi-Language Projects

Parity's structural checks are language agnostic when the project supplies matching file extensions, test suffixes, and coverage files. The public sample repositories listed in `docs/SAMPLES.md` cover PHP, Laravel-style PHP, Vite/TypeScript, AdonisJS-style TypeScript, Rust, PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo.

Use language-specific coverage tooling to produce a supported report, then point `coverage_xml` at that report. Prefer attribution-capable formats first, for example `coverage_xml: [.parity/per-test, parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]`, so Parity uses native or normalized per-test attribution when available and falls back to portable single-file formats otherwise.

Common starting points:

| Ecosystem | Typical coverage artifact | Notes |
| --- | --- | --- |
| PHP + PHPUnit | `coverage-xml/`, `clover.xml` | Use PHPUnit XML for attribution; Clover for fallback. |
| PHP + Pest | `coverage-xml/`, `clover.xml` | Pest can also use `->covers()` for ownership link checks. |
| JavaScript + Jest | `clover.xml`, custom `parity-coverage.json` | Use Parity JSON when a converter can provide per-test attribution. |
| JavaScript + Mocha/NYC | `clover.xml`, custom `parity-coverage.json` | Clover supports per-file thresholds only. |
| TypeScript + Vitest | `clover.xml`, `cobertura.xml`, custom `parity-coverage.json` | Works with Vite-style layouts by changing extensions and suffixes. |
| Rust + Cargo | `cobertura.xml`, custom `parity-coverage.json` | Cobertura is the common portable aggregate format. |

## `parity test`

When a project cannot emit native per-test attribution, configure `parity test` to run each expected test file individually and write Parity's native per-test report directory.

```yaml
test:
  command: "./vendor/bin/pest {test_abs} --coverage-clover={coverage}"
  coverage: ".parity/tmp/{slug}.xml"
  reports: ".parity/per-test"
```

Then put that directory first in `coverage_xml`:

```yaml
coverage_xml: [.parity/per-test, parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
```
