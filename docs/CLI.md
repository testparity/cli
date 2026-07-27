# CLI Reference

Specs: S001, S008, S010, S011

Parity ships as a Laravel Zero CLI with three public commands.

For the complete implementation map, including internal services and output contracts, see `docs/REFERENCE.md`.

## `parity init`

Creates a starter `parity.yaml` in the current directory. If the file already exists, Parity leaves it unchanged and exits successfully.

```bash
parity init
```

## `parity check`

Reads an existing coverage report and evaluates configured parity rules. This command does not run a project's test suite; generate coverage first with the framework's native tooling or with `parity test`.

```bash
parity check
parity check --format=json
parity check --show-tests
parity check --config=path/to/parity.yaml
```

## `parity test`

Runs each expected belonging test individually, normalizes the resulting single-test coverage artifact into Parity's per-test report directory, and runs `parity check` against that directory by default.

```bash
parity test
parity test --format=json
parity test --show-tests
parity test --output=.parity/per-test
parity test --timeout=600
parity test --no-check
parity test --config=path/to/parity.yaml
```

Required configuration:

```yaml
test:
  command: "./vendor/bin/pest {test_abs} --coverage-clover={coverage}"
  coverage: ".parity/tmp/{slug}.xml"
  reports: ".parity/per-test"
  timeout: 300
```

Supported placeholders are `{test}`, `{test_abs}`, `{coverage}`, `{slug}`, and `{project_root}`. The `reports` key is optional and defaults to `.parity/per-test`; `timeout` defaults to 300 seconds and can be overridden with `--timeout`.

Report generation is staged and replaces the previous complete set only after every isolated run succeeds. Temporary runner artifacts are removed after every attempt. Unsafe, nested-symlink, overlapping, or unowned non-empty output paths are rejected before test execution.

Exit codes:

| Code | Meaning |
|------|---------|
| 0 | All enforced rules passed, reports were generated in `--no-check` mode, or `init` completed without overwriting an existing config. |
| 1 | Required input was missing, a test failed or timed out, usable coverage was not produced, coverage thresholds failed, or an enforced rule failed. |

## Coverage Preference

Prefer coverage formats that include per-line and per-test attribution when a language or framework can produce them. For PHP, PHPUnit XML directories generated with `--coverage-xml` are more useful than Clover XML because they support matched coverage and attribution checks. When only aggregate Clover or Cobertura is available, `parity test` can isolate each belonging test and normalize that artifact into attribution-capable reports.
