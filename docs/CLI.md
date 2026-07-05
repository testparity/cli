# CLI Reference

Specs: S001, S008, S010

Parity ships as a Laravel Zero CLI with two public commands.

For the complete implementation map, including internal services and output contracts, see `docs/REFERENCE.md`.

## `parity init`

Creates a starter `parity.yaml` in the current directory. If the file already exists, Parity leaves it unchanged and exits successfully.

```bash
parity init
```

## `parity check`

Reads an existing coverage report and evaluates configured parity rules. Parity does not run a project's test suite; generate coverage first with the framework's native tooling.

```bash
parity check
parity check --format=json
parity check --show-tests
parity check --config=path/to/parity.yaml
```

Exit codes:

| Code | Meaning |
|------|---------|
| 0 | All enforced rules passed, or `init` completed without overwriting an existing config. |
| 1 | Required input was missing, coverage thresholds failed, or an enforced rule failed. |

## Coverage Preference

Prefer coverage formats that include per-line and per-test attribution when a language or framework can produce them. For PHP, PHPUnit XML directories generated with `--coverage-xml` are more useful than Clover XML because they support matched coverage and attribution checks. Clover XML remains supported as a portable fallback for many ecosystems.
