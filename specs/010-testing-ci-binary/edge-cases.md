# S010 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S010-EC-001 | Pest test with no matching spec ID in group annotation | Test is still valid but listed as untraceable in coverage reports; maintainers SHOULD add spec ID |
| S010-EC-002 | Coverage report shows any file below configured min_coverage threshold | CI coverage job MUST fail with exit code 1 |
| S010-EC-003 | Box PHAR compilation fails due to missing file in box.json | Build MUST fail with descriptive error; artifact not uploaded |
| S010-EC-004 | Pint auto-fixes a file during CI lint check | CI MUST still fail the lint job; fixes MUST be committed in a separate PR |
| S010-EC-005 | Release script run on dirty git state (uncommitted changes) | Script MUST abort with error message: "uncommitted changes detected, commit or stash first" |
| S010-EC-006 | Composer dependencies unavailable (network issue) during PHAR build | Build MUST fail; CI artifact not uploaded; error message indicates dependency failure |
| S010-EC-007 | Git tag already exists when release script runs | Script MUST abort with error message: "tag v{version} already exists" |
| S010-EC-008 | GitHub Release asset upload fails | Workflow SHOULD retry up to 3 times with exponential backoff before failing |
| S010-EC-009 | CI runs on a fork (pull request from fork) | Pipeline MUST NOT upload artifacts to external services or expose secrets |
| S010-EC-010 | PHPUnit XML coverage data not found by Parity | Parity check MUST produce warning but MUST NOT fail (missing coverage is informational) |
