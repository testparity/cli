# Contributing to Parity

Thanks for helping. Bug reports, sample requests, docs fixes, and code changes are all welcome.

By taking part you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md). Report security problems through the [security policy](SECURITY.md), not a public issue.

## Before you start

- **Bugs:** open an issue with the bug report template. Include `parity --version`, your `parity.yaml`, the command you ran, and the output.
- **New test runners or languages:** open a framework support or sample request first, so we can agree on the coverage format before you write code.
- **Small fixes** such as typos or clearer error messages can go straight to a pull request.

## Set up

You need PHP 8.4 or newer and Composer.

```bash
git clone https://github.com/testparity/cli.git
cd cli
composer install
```

## Run the checks

```bash
./vendor/bin/pint --test   # code style
./vendor/bin/pest          # tests, no coverage driver needed
composer validate --strict
composer audit
```

CI also measures coverage and runs Parity on itself. To do the same locally, install Xdebug or PCOV, then run:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml
php parity check
```

`php parity check` reads the root `parity.yaml`, so every file in `app/` needs a matching test that covers it.

## Making a change

- Add or update a test for every behavior change. Feature tests live in `tests/Feature`, unit tests in `tests/Unit`.
- Keep the style Pint enforces. Run `./vendor/bin/pint` to fix it automatically.
- Specs in `specs/` describe the intended behavior. Tests and classes name the specs they cover in a `Specs:` comment; keep those references accurate when you touch them.
- Add a line under `## [Unreleased]` in `CHANGELOG.md` for anything users will notice.
- If you change output, numbers, or configuration, update `README.md` and the matching page in `docs/`.

## Samples

The public sample repositories under [github.com/testparity](https://github.com/testparity) run real test suites in CI. The `samples/` directory in this repository holds committed coverage fixtures that the test suite reads. If a change affects how coverage is parsed, run `php parity check --config=samples/<name>/parity.yaml` for the affected fixtures and say in the pull request whether any numbers moved.

## Pull requests

- Keep each pull request focused on one change.
- Describe what changed and why, and how you tested it.
- CI must pass before review.

## Releases

Maintainers cut releases with `dev/release-version.sh`. Pushing a `v*` tag builds the PHAR, smoke-tests the Packagist install, and publishes the GitHub release.
