# S010 Success Criteria

| ID | Criterion |
|----|-----------|
| S010-SC-001 | All Pest tests pass with exit code 0 and produce PHPUnit XML plus Clover coverage |
| S010-SC-002 | Dependency validation, audit, Pint, Pest, Parity self-check, result artifact, and PHAR verification report green on `main` |
| S010-SC-003 | PHAR binary is executable via `php parity.phar --version` and produces correct version output |
| S010-SC-004 | Release artifacts (PHAR + SHA-256 checksum) are attached to the GitHub Release |
| S010-SC-005 | Sample projects cover plain PHP, Laravel-style PHP, Vite/TypeScript, AdonisJS-style TypeScript, and Rust-style layouts |
| S010-SC-006 | The tracked version, annotated tag, PHAR output, and Packagist-installed CLI report the same SemVer |
| S010-SC-007 | Changelog entry follows Keep a Changelog format and contains curated, non-placeholder release notes |
| S010-SC-008 | The exact Packagist release passes both `parity check` and `parity test` before its GitHub Release is published |
