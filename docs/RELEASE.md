# Release Checklist

Specs: S010

Run these gates before tagging a public release or building a PHAR manually:

```bash
composer validate --strict
composer install
composer audit --locked --no-interaction
./vendor/bin/pint --test
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never
php parity check --format=json
./vendor/bin/box compile --no-interaction
php parity.phar --version
php parity.phar check --format=json
shasum -a 256 parity.phar > parity.phar.sha256
npm --prefix ../parity-website run build
npm --prefix ../parity-website audit --audit-level=high
```

Prepare a release:

1. Add curated notes beneath `## [Unreleased]` in `CHANGELOG.md`.
2. Choose the SemVer bump and preview it, for example `./dev/release-version.sh minor --dry-run`.
3. Run `./dev/release-version.sh minor --push` after the preview and release gates pass.

Do not create or push a release tag manually. The release script promotes the curated notes into a dated version section, updates `VERSION`, verifies both files, creates the release commit and annotated tag, and pushes the commit and tag atomically.

Automated tagged release:

- Pushing a `v*` tag runs `.github/workflows/release.yml`.
- CI rejects a tag unless it exactly matches the tracked `VERSION` and a non-placeholder `CHANGELOG.md` section.
- The workflow reruns the release gates, builds `parity.phar`, generates `parity.phar.sha256`, and creates a draft GitHub release with both assets attached.
- The release stays draft until Packagist exposes the tagged version and the public install smoke proves both `parity check` and `parity test` against that exact installed version.
- When the smoke passes, the workflow publishes the GitHub release automatically.
- A weekly no-version smoke checks the latest stable Packagist package. It runs `parity check` for pre-v1.2.0 compatibility and enforces both commands from v1.2.0 onward; exact tagged release smokes never skip `parity test`.

Repository hygiene checks:

- No generated coverage reports are tracked.
- No local IDE, agent, or analysis output is tracked.
- Root `parity.yaml` passes against freshly generated `coverage-xml/`.
- `samples/*/parity.yaml` runs with the current CLI.
- Website docs build with `npm run build` from `../parity-website`.
- PHAR size remains under the S010 target of 30MB.
- `parity.phar.sha256` is generated for manual release verification.
- The Packagist-installed CLI and PHAR both report the exact release tag from `parity --version`.
- `Release` and `CI` are green before marking a tag or branch ready for public hand-off.

The Pest suite includes `tests/Feature/SamplesParityTest.php`, which executes `parity check --format=json` against the PHP, Laravel, Vite, AdonisJS, Rust, PHPUnit, Pest, Jest, Mocha, Vitest, and Cargo sample configs.
