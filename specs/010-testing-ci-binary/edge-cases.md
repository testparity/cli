# S010 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S010-EC-001 | Behavior-focused Pest test has no `// Specs:` traceability comment | Test remains executable, but review SHOULD require the missing spec reference before merge |
| S010-EC-002 | Coverage report shows any file below configured min_coverage threshold | CI coverage job MUST fail with exit code 1 |
| S010-EC-003 | Box PHAR compilation fails due to missing file in box.json | Build MUST fail with descriptive error; artifact not uploaded |
| S010-EC-004 | Pint detects a style violation during its non-mutating CI check | CI MUST fail and report the file; formatting fixes MUST be committed separately |
| S010-EC-005 | Release script run with tracked or untracked changes | Script MUST abort before changing release files and tell the maintainer to commit or stash |
| S010-EC-006 | Composer dependencies unavailable (network issue) during PHAR build | Build MUST fail; CI artifact not uploaded; error message indicates dependency failure |
| S010-EC-007 | Git tag already exists when release script runs | Script MUST abort before changing `VERSION`, `CHANGELOG.md`, or git history |
| S010-EC-008 | GitHub Release asset upload fails | Workflow MUST fail without publishing the draft Release; a maintainer can safely retry the workflow |
| S010-EC-009 | CI runs on a fork (pull request from fork) | Pipeline MUST NOT upload artifacts to external services or expose secrets |
| S010-EC-010 | None of the configured coverage candidates exists | Parity check MUST identify every attempted path and fail with exit code 1 |
| S010-EC-011 | Release script runs from a branch other than `main` or detached HEAD | Script MUST abort before changing release files or git history |
