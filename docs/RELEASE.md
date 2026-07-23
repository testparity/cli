# Release Checklist

Specs: S010

Run these gates before tagging a public release or building a PHAR manually:

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

Automated tagged releases:

- Pushing a `v*` tag runs `.github/workflows/release.yml`.
- The workflow reruns the release gates, builds `parity.phar`, generates `parity.phar.sha256`, and creates a draft GitHub release with both assets attached.
- The release stays draft until Packagist exposes the tagged version and the public install smoke passes against that exact version.
- When the smoke passes, the workflow publishes the GitHub release automatically.

Repository hygiene checks:

- No generated coverage reports are tracked.
- No local IDE, agent, or analysis output is tracked.
- Root `parity.yaml` passes against freshly generated `coverage-xml/`.
- `samples/*/parity.yaml` runs with the current CLI.
- Website docs build with `npm run build` from `../parity-website`.
- PHAR size remains under the S010 target of 30MB.
- `parity.phar.sha256` is generated for manual release verification.
- `Release` and `CI` are green before marking a tag or branch ready for public hand-off.

The Pest suite includes `tests/Feature/SamplesParityTest.php`, which executes `parity check --format=json` against the PHP, Laravel, Vite, AdonisJS, Rust, PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo sample configs.
