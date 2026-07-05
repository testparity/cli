# Samples

Specs: S010-FR-019, S010-AS-011

These samples validate Parity against different project layouts, languages, and test runners.

Two sample styles are included:

- Layout fixtures such as `php`, `laravel`, `vite`, `adonisjs`, and `rust` use committed coverage XML reports so the CLI can be exercised without installing every ecosystem's test runner.
- Runner samples such as `phpunit`, `pest`, `jest`, `mocha`, `vitest`, and `cargo` include runnable applications under `code/` plus `result.md` files that record native test and Parity results.

Run from the `parity/` package root:

```bash
php parity check --config=samples/php/parity.yaml
php parity check --config=samples/laravel/parity.yaml
php parity check --config=samples/vite/parity.yaml
php parity check --config=samples/adonisjs/parity.yaml
php parity check --config=samples/rust/parity.yaml
php parity check --config=samples/phpunit/parity.yaml
php parity check --config=samples/pest/parity.yaml
php parity check --config=samples/jest/parity.yaml
php parity check --config=samples/mocha/parity.yaml
php parity check --config=samples/vitest/parity.yaml
php parity check --config=samples/cargo/parity.yaml
```

The PHP ecosystem can additionally use PHPUnit XML directories for per-test attribution. These fixtures cover Clover XML and Cobertura XML because both are portable across common non-PHP ecosystems.

## Runner sample matrix

| Sample | Language | Native command | Coverage used by Parity |
| --- | --- | --- | --- |
| `phpunit` | PHP | `composer test` | PHPUnit Clover XML |
| `pest` | PHP | `composer test` | Pest Clover XML plus Pest `->covers()` link validation |
| `jest` | JavaScript | `npm test` | Jest Clover XML |
| `mocha` | JavaScript | `npm test`, `npm run coverage` | NYC Clover XML |
| `vitest` | TypeScript | `npm test`, `npm run coverage` | Vitest Clover XML |
| `cargo` | Rust | `cargo test` | Cobertura XML fixture |
