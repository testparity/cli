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

coverage_xml: [coverage-xml, clover.xml, cobertura.xml]
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

Parity's structural checks are language agnostic when the project supplies matching file extensions, test suffixes, and coverage files. The `samples/` directory contains minimal PHP, Laravel-style PHP, Vite/TypeScript, AdonisJS-style TypeScript, and Rust configurations, plus runnable tool samples for PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo.

Use language-specific coverage tooling to produce a supported report, then point `coverage_xml` at that report. Prefer high-detail formats first, for example `coverage_xml: [coverage-xml, clover.xml, cobertura.xml]`, so Parity uses PHPUnit XML attribution when available and falls back to portable single-file formats otherwise.
