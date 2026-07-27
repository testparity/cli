# S010 Acceptance Scenarios

### S010-AS-001 Full Test Suite Passes Locally [P1]

**Given** a developer has run `composer install` in the `parity/` directory
**When** the developer runs `XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml`
**Then** all Pest tests **MUST** pass with exit code 0

### S010-AS-002 CI Pipeline Runs on Push to Main [P1]

**Given** a developer pushes a commit to the `main` branch
**When** the push is received by GitHub
**Then** the CI job **MUST** execute dependency validation, Pint, covered Pest tests, Parity self-check, and PHAR verification in sequence
**And** it **MUST** upload the Parity result when the self-check is reached

### S010-AS-003 CI Pipeline Runs on Pull Requests [P1]

**Given** a developer opens a pull request against `main`
**When** the pull request is opened or updated with a new push
**Then** the same release-quality CI job **MUST** execute

### S010-AS-004 CI Lint Job Fails on Style Violations [P1]

**Given** a developer commits code with Laravel Pint style violations
**When** CI runs `./vendor/bin/pint --test`
**Then** the job **MUST** fail with a non-zero exit code and report the violations

### S010-AS-005 CI Test Job Fails on Test Failures [P1]

**Given** a developer introduces a failing unit test
**When** CI runs `./vendor/bin/pest`
**Then** the job **MUST** fail with a non-zero exit code and report the failing test(s)

### S010-AS-006 PHAR Compiles Successfully [P1]

**Given** a developer runs `./vendor/bin/box compile --no-interaction` in the `parity/` directory
**When** Box has finished processing
**Then** a `parity.phar` file **MUST** exist in the `parity/` directory
**And** the PHAR **MUST** be a valid PHP archive executable via `php parity.phar --version`

### S010-AS-007 PHAR Runs Parity Check Successfully [P1]

**Given** the PHAR binary has been built
**When** a user runs `php parity.phar check`
**Then** the command **MUST** execute the `check` command and produce valid output

### S010-AS-008 Release Script Updates VERSION and CHANGELOG [P1]

**Given** the repository is on the `main` branch with no uncommitted changes and curated notes beneath `## [Unreleased]`
**When** a maintainer runs `./dev/release-version.sh minor`
**Then** the `VERSION` file **MUST** contain the new `v{MAJOR}.{MINOR}.{PATCH}` version string
**And** `CHANGELOG.md` **MUST** promote the curated notes into a new entry with the current date and version
**And** a release commit and matching annotated tag **MUST** exist locally
**And** no remote push **MUST** occur

### S010-AS-009 Release Script Creates Git Tag [P1]

**Given** the repository is on the `main` branch with no uncommitted changes
**When** a maintainer runs `./dev/release-version.sh minor --push`
**Then** the release commit and annotated `v{version}` tag **MUST** be pushed atomically and resolve to the same commit locally and on the remote

### S010-AS-010 Release Script Creates GitHub Release [P1]

**Given** a release tag with matching tracked version and changelog metadata has been pushed
**When** the GitHub Actions release workflow triggers
**Then** a draft GitHub Release **MUST** be created with `parity.phar` and `parity.phar.sha256`
**And** the exact tag **MUST** become installable from Packagist
**And** the installed CLI **MUST** report that tag and pass both `parity check` and `parity test`
**And** only then **MUST** the GitHub Release be published

### S010-AS-011 Sample Project Configs Pass [P1]

**Given** the repository includes sample configs under `samples/php`, `samples/laravel`, `samples/vite`, `samples/adonisjs`, and `samples/rust`
**When** the test suite runs the sample matrix through `parity check --format=json`
**Then** every sample config **MUST** pass with exit code 0
**And** each sample **MUST** demonstrate at least one supported path, extension, suffix, or namespace mapping convention

### S010-AS-012 Release Metadata Mismatch Is Rejected [P1]

**Given** a release tag does not equal the tracked `VERSION` or lacks curated changelog notes
**When** the GitHub Actions release workflow starts
**Then** release metadata verification **MUST** fail before dependencies are installed or artifacts are built
