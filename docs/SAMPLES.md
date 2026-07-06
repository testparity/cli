# Samples

Each sample repository demonstrates the same Parity proof in a different ecosystem:

| Sample | Focus | Repository |
| --- | --- | --- |
| PHP | Plain PHP source and tests | https://github.com/testparity/php-sample |
| Laravel | Laravel-style service tests | https://github.com/testparity/laravel-sample |
| TypeScript | Vite-style TypeScript utility | https://github.com/testparity/typescript-sample |
| AdonisJS | AdonisJS-style TypeScript service | https://github.com/testparity/adonisjs-sample |
| Rust | Plain Rust source and tests | https://github.com/testparity/rust-sample |
| Cargo | Runnable Cargo project | https://github.com/testparity/cargo-sample |
| PHPUnit | Runnable PHPUnit project | https://github.com/testparity/phpunit-sample |
| Pest | Runnable Pest project | https://github.com/testparity/pest-sample |
| Jest | Runnable Jest project | https://github.com/testparity/jest-sample |
| Mocha | Runnable Mocha project | https://github.com/testparity/mocha-sample |
| Vitest | Runnable Vitest project | https://github.com/testparity/vitest-sample |

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
