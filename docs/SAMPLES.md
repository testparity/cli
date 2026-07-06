# Samples

Each sample repository demonstrates the same Parity proof in a different ecosystem. The repositories are intentionally split so each one can install the public Packagist package during CI and prove Parity works without private tokens or local source checkout.

| Sample | Language/framework | Test runner | Coverage input | Repository |
| --- | --- | --- | --- | --- |
| PHP | Plain PHP | Fixture coverage | Parity JSON | https://github.com/testparity/php-sample |
| Laravel | Laravel-style PHP | Fixture coverage | Parity JSON | https://github.com/testparity/laravel-sample |
| TypeScript | TypeScript utility | Fixture coverage | Parity JSON | https://github.com/testparity/typescript-sample |
| AdonisJS | AdonisJS-style TypeScript | Fixture coverage | Parity JSON | https://github.com/testparity/adonisjs-sample |
| Rust | Plain Rust | Fixture coverage | Parity JSON | https://github.com/testparity/rust-sample |
| Cargo | Cargo project | Cargo | Parity JSON | https://github.com/testparity/cargo-sample |
| PHPUnit | PHP | PHPUnit | Parity JSON | https://github.com/testparity/phpunit-sample |
| Pest | PHP | Pest | Parity JSON | https://github.com/testparity/pest-sample |
| Jest | JavaScript | Jest | Parity JSON | https://github.com/testparity/jest-sample |
| Mocha | JavaScript | Mocha + NYC | Parity JSON | https://github.com/testparity/mocha-sample |
| Vitest | TypeScript | Vitest | Parity JSON | https://github.com/testparity/vitest-sample |

## What Each Sample Proves

Every sample is built around the same coverage shape:

| Scope | Coverage |
| --- | ---: |
| Project/global coverage | 80% |
| Weaker file, all tests | 70% |
| Weaker file, matching test only | 40% |
| Stronger file, all tests | 90% |
| Stronger file, matching test only | 90% |

This proves that a project can have healthy global coverage while a file's own matching test barely covers the file. Parity catches that difference with `matched-coverage` and shows incidental coverage with `coverage-attribution`.

## CI Contract

Every sample CI installs Parity from Packagist:

```bash
composer global require testparity/parity --prefer-dist --no-progress --no-interaction
parity check --format=json
```

That matters because the samples prove the public release path, not only the source checkout in this repository.

## Language-Neutral Attribution

The samples use `parity-coverage.json`, a language-neutral coverage attribution fixture. It records global coverage, per-file coverage, executable line counts, and which tests covered each line.

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

Use PHPUnit XML directories when your PHP test runner can generate them directly. Use `parity-coverage.json` when you want per-test attribution from another ecosystem or a custom converter. See `docs/PARITY-COVERAGE-JSON.md` for the full schema and converter example.

For fallback-only checks, Parity can also read Clover XML and Cobertura XML. Those formats support global and per-file coverage thresholds but do not contain enough test attribution for `matched-coverage`.
