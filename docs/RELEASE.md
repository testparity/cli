# Release Checklist

Specs: S010

Run these gates before pushing a public release branch or building a PHAR manually:

```bash
composer validate --strict
composer install
./vendor/bin/pint --test
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never
php parity check --format=json
./vendor/bin/box compile --no-interaction
php parity.phar --version
php parity.phar check --format=json
shasum -a 256 parity.phar > parity.phar.sha256
npm --prefix ../parity-website run build
```

Repository hygiene checks:

- No generated coverage reports are tracked.
- No local IDE, agent, or analysis output is tracked.
- Root `parity.yaml` passes against freshly generated `coverage-xml/`.
- `samples/*/parity.yaml` runs with the current CLI.
- Website docs build with `npm run build` from `../parity-website`.
- PHAR size remains under the S010 target of 30MB.
- `parity.phar.sha256` is generated for manual release verification.

The Pest suite includes `tests/Feature/SamplesParityTest.php`, which executes `parity check --format=json` against the PHP, Laravel, Vite, AdonisJS, Rust, PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo sample configs.
