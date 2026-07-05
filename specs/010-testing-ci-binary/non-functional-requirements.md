# S010 Non-Functional Requirements

### Performance

S010-NF-001 [P1] The full test suite **MUST** execute in under 60 seconds on a standard developer machine (PHP 8.2, 8GB RAM).
S010-NF-002 [P1] The CI pipeline **MUST** complete all jobs within 5 minutes on a GitHub-hosted runner.

### Binary Size

S010-NF-003 [P2] The compiled PHAR binary **SHOULD** remain under 30MB in size for the current Laravel Zero distribution. If future dependencies push the binary above 30MB, release notes **MUST** call out the size increase and the reason.

### Code Quality

S010-NF-004 [P2] Laravel Pint style violations **MUST NOT** be present in any commit to `main`.
S010-NF-005 [P2] All code **SHOULD** pass `phpstan level 5` or higher (if configured).
