# S010 Success Criteria

| ID | Criterion |
|----|-----------|
| S010-SC-001 | All Pest tests pass with exit code 0 (`php artisan test`) |
| S010-SC-002 | All CI jobs (lint, test, coverage) report green status on the `main` branch |
| S010-SC-003 | PHAR binary is executable via `php parity.phar --version` and produces correct version output |
| S010-SC-004 | Release artifacts (PHAR + SHA-256 checksum) are attached to the GitHub Release |
| S010-SC-005 | Sample projects cover plain PHP, Laravel-style PHP, Vite/TypeScript, AdonisJS-style TypeScript, and Rust-style layouts |
| S010-SC-006 | GitHub Release body contains the changelog entry for the released version |
| S010-SC-007 | Changelog entry follows Keep a Changelog format and includes at least one change category |
