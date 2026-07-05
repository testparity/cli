# S010: Testing, CI/CD & Binary Distribution

| Field | Value |
|-------|-------|
| Spec | S010 |
| Feature | Testing, CI/CD & Binary Distribution |
| Date | 2026-04-24 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Parity's quality gates span three coordinated systems: a Pest-based test suite validating all rules, coverage readers, and CLI behavior; a GitHub Actions CI pipeline running lint, test, and coverage jobs on every push and pull request; and a Box-based PHAR compilation pipeline that packages the application into a standalone distributable binary. Laravel Pint enforces code style, and the release process governs version bumping, changelog generation, git tagging, and GitHub Release creation.

This specification defines the complete contract for all four areas: test suite structure and coverage expectations, CI/CD workflow jobs and triggers, binary distribution via Box PHAR, and the end-to-end release process. It serves as the source of truth for implementation (S010-FR), acceptance testing (S010-AS), QA edge-case coverage (S010-EC), non-functional constraints (S010-NF), and measurable success criteria (S010-SC).

## User Scenarios

S010-US-001 [P1] As a developer, I want to run the full test suite via `php artisan test` so that I can verify all Parity functionality locally before pushing.

S010-US-002 [P1] As a CI pipeline, I want to run lint, test, and coverage jobs in parallel so that I can get fast feedback on pull requests.

S010-US-003 [P1] As a developer, I want the CI pipeline to fail if code style violations exist so that style drift is never merged.

S010-US-004 [P1] As a maintainer, I want to trigger a version bump and have it automatically update CHANGELOG.md, create a git tag, and draft a GitHub Release so that releases are consistent and auditable.

S010-US-005 [P2] As a user, I want to download a standalone PHAR binary so that I can use Parity without installing PHP or Composer.

S010-US-006 [P2] As a package author, I want to verify that my plugin rules are tested in Parity's CI so that custom rule authors have a reference implementation.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S010-FR-001 | Functional | P1 | Test suite organization | Draft |
| S010-FR-002 | Functional | P1 | Test naming conventions | Draft |
| S010-FR-003 | Functional | P1 | Pest configuration | Draft |
| S010-FR-004 | Functional | P1 | Coverage threshold enforcement | Draft |
| S010-FR-005 | Functional | P1 | Spec ID traceability in tests | Draft |
| S010-FR-006 | Functional | P1 | CI pipeline triggers | Draft |
| S010-FR-007 | Functional | P1 | CI lint job | Draft |
| S010-FR-008 | Functional | P1 | CI test job | Draft |
| S010-FR-009 | Functional | P1 | CI coverage job | Draft |
| S010-FR-010 | Functional | P1 | CI artifact publication | Draft |
| S010-FR-011 | Functional | P1 | Box PHAR compilation | Draft |
| S010-FR-012 | Functional | P1 | PHAR distribution mechanism | Draft |
| S010-FR-013 | Functional | P1 | PHAR version injection | Draft |
| S010-FR-014 | Functional | P1 | Laravel Pint integration | Draft |
| S010-FR-015 | Functional | P1 | Release version bumping | Draft |
| S010-FR-016 | Functional | P1 | Changelog generation | Draft |
| S010-FR-017 | Functional | P1 | Git tagging | Draft |
| S010-FR-018 | Functional | P1 | GitHub Release creation | Draft |
| S010-FR-019 | Functional | P1 | Sample project matrix | Draft |
| S010-AS-001 | Acceptance | P1 | Full test suite passes locally | Draft |
| S010-AS-002 | Acceptance | P1 | CI pipeline runs on push to main | Draft |
| S010-AS-003 | Acceptance | P1 | CI pipeline runs on pull requests | Draft |
| S010-AS-004 | Acceptance | P1 | CI lint job fails on style violations | Draft |
| S010-AS-005 | Acceptance | P1 | CI test job fails on test failures | Draft |
| S010-AS-006 | Acceptance | P1 | PHAR compiles successfully | Draft |
| S010-AS-007 | Acceptance | P1 | PHAR runs parity check successfully | Draft |
| S010-AS-008 | Acceptance | P1 | Release script updates VERSION and CHANGELOG | Draft |
| S010-AS-009 | Acceptance | P1 | Release script creates git tag | Draft |
| S010-AS-010 | Acceptance | P1 | Release script creates GitHub Release | Draft |
| S010-AS-011 | Acceptance | P1 | Sample project configs pass | Draft |
| S010-EC-001 | Edge Case | P1 | Pest test group with no matching spec ID | Draft |
| S010-EC-002 | Edge Case | P1 | Coverage below threshold triggers CI failure | Draft |
| S010-EC-003 | Edge Case | P1 | Box PHAR compilation failure | Draft |
| S010-EC-004 | Edge Case | P2 | Pint fixes file during CI lint check | Draft |
| S010-EC-005 | Edge Case | P1 | Release script run on dirty git state | Draft |
| S010-EC-006 | Edge Case | P2 | Composer dependencies unavailable during PHAR build | Draft |
| S010-NF-001 | Non-Functional | P1 | Test suite executes in under 60 seconds | Draft |
| S010-NF-002 | Non-Functional | P1 | CI pipeline completes in under 5 minutes | Draft |
| S010-NF-003 | Non-Functional | P2 | PHAR binary under 30MB target | Draft |
| S010-SC-001 | Success | P1 | All Pest tests pass | Draft |
| S010-SC-002 | Success | P1 | All CI jobs green on main branch | Draft |
| S010-SC-003 | Success | P1 | PHAR binary is executable and self-contained | Draft |
| S010-SC-004 | Success | P1 | Release artifacts signed and verifiable | Draft |
| S010-SC-005 | Success | P1 | Sample projects cover supported language/framework configurations | Draft |

## Cross-Spec Dependencies

- **Depends on:** S001 (CLI Commands -- `parity check` and `parity init` are exercised by the test suite and CI), S002 (Rules System -- rule implementations are unit-tested), S003 (Coverage Readers -- coverage readers are tested with fixture files), S004 (Coverage Linkers -- linker implementations have dedicated Pest tests), S005 (Plugin System -- plugin loading is exercised in integration tests)
- **Required by:** N/A (this is a foundational infrastructure spec)
