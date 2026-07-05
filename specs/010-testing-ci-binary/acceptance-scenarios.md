# S010 Acceptance Scenarios

### S010-AS-001 Full Test Suite Passes Locally [P1]

**Given** a developer has run `composer install` in the `parity/` directory
**When** the developer runs `php artisan test` from the `parity/` directory
**Then** all Pest tests **MUST** pass with exit code 0

### S010-AS-002 CI Pipeline Runs on Push to Main [P1]

**Given** a developer pushes a commit to the `main` branch
**When** the push is received by GitHub
**Then** the CI pipeline **MUST** execute the lint, test, and coverage jobs in sequence

### S010-AS-003 CI Pipeline Runs on Pull Requests [P1]

**Given** a developer opens a pull request against `main`
**When** the pull request is opened or updated with a new push
**Then** the CI pipeline **MUST** execute the lint, test, and coverage jobs

### S010-AS-004 CI Lint Job Fails on Style Violations [P1]

**Given** a developer commits code with Laravel Pint style violations
**When** the CI lint job runs `composer pint --test`
**Then** the job **MUST** fail with a non-zero exit code and report the violations

### S010-AS-005 CI Test Job Fails on Test Failures [P1]

**Given** a developer introduces a failing unit test
**When** the CI test job runs `php artisan test`
**Then** the job **MUST** fail with a non-zero exit code and report the failing test(s)

### S010-AS-006 PHAR Compiles Successfully [P1]

**Given** a developer runs `composer box compile` in the `parity/` directory
**When** Box has finished processing
**Then** a `parity.phar` file **MUST** exist in the `parity/` directory
**And** the PHAR **MUST** be a valid PHP archive executable via `php parity.phar --version`

### S010-AS-007 PHAR Runs Parity Check Successfully [P1]

**Given** the PHAR binary has been built
**When** a user runs `php parity.phar check`
**Then** the command **MUST** execute the `check` command and produce valid output

### S010-AS-008 Release Script Updates VERSION and CHANGELOG [P1]

**Given** the repository is on the `main` branch with no uncommitted changes
**When** a maintainer runs `./dev/release-version.sh minor`
**Then** the `VERSION` file **MUST** contain the new version string
**And** `CHANGELOG.md` **MUST** contain a new entry with the current date and version

### S010-AS-009 Release Script Creates Git Tag [P1]

**Given** the repository is on the `main` branch with no uncommitted changes
**When** a maintainer runs `./dev/release-version.sh minor --push`
**Then** a git tag matching `v{version}` **MUST** exist locally and on the remote

### S010-AS-010 Release Script Creates GitHub Release [P1]

**Given** a git tag `v1.0.0` has been pushed to the remote
**When** the GitHub Actions release workflow triggers
**Then** a GitHub Release titled `v1.0.0` **MUST** appear in the repository
**And** the `parity.phar` binary **MUST** be attached as a Release asset

### S010-AS-011 Sample Project Configs Pass [P1]

**Given** the repository includes sample configs under `samples/php`, `samples/laravel`, `samples/vite`, `samples/adonisjs`, and `samples/rust`
**When** the test suite runs the sample matrix through `parity check --format=json`
**Then** every sample config **MUST** pass with exit code 0
**And** each sample **MUST** demonstrate at least one supported path, extension, suffix, or namespace mapping convention
