# S010: Testing, CI/CD & Binary Distribution

| Field | Value |
|-------|-------|
| Spec | S010 |
| Feature | Testing, CI/CD & Binary Distribution |
| Date | 2026-04-24 |
| Status | Implemented |
| Author | spec-writer-agent |

## Overview

Parity's quality gates span three coordinated systems: a Pest-based test suite validating rules, coverage readers, release automation, samples, and CLI behavior; a GitHub Actions CI job running the release-quality gates on branch pushes and pull requests; and a Box-based PHAR pipeline that packages the application for users with PHP 8.4 and zlib. Laravel Pint enforces code style, and the release process governs curated changelog promotion, version verification, atomic tagging, Packagist smoke testing, and GitHub Release publication.

This specification defines the complete contract for all four areas: test suite structure and coverage expectations, CI/CD workflow jobs and triggers, binary distribution via Box PHAR, and the end-to-end release process. It serves as the source of truth for implementation (S010-FR), acceptance testing (S010-AS), QA edge-case coverage (S010-EC), non-functional constraints (S010-NF), and measurable success criteria (S010-SC).

## User Scenarios

S010-US-001 [P1] As a developer, I want one documented covered Pest command so that I can verify all Parity functionality locally before pushing.

S010-US-002 [P1] As a CI pipeline, I want to run dependency, style, test, self-check, artifact, and PHAR gates in a deterministic sequence so that failures remain attributable.

S010-US-003 [P1] As a developer, I want the CI pipeline to fail if code style violations exist so that style drift is never merged.

S010-US-004 [P1] As a maintainer, I want a curated release command to align the changelog, source version, annotated tag, PHAR, Packagist package, and GitHub Release so that publication is consistent and auditable.

S010-US-005 [P2] As a user with PHP 8.4 and zlib, I want to download a standalone PHAR binary so that I can use Parity without adding Composer dependencies to my project.

S010-US-006 [P2] As a package author, I want to verify that my plugin rules are tested in Parity's CI so that custom rule authors have a reference implementation.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S010-FR-001 | Functional | P1 | Test suite organization | Implemented |
| S010-FR-002 | Functional | P1 | Test naming conventions | Implemented |
| S010-FR-003 | Functional | P1 | Pest configuration | Implemented |
| S010-FR-004 | Functional | P1 | Coverage report generation | Implemented |
| S010-FR-005 | Functional | P1 | Spec ID traceability in tests | Implemented |
| S010-FR-006 | Functional | P1 | CI pipeline triggers | Implemented |
| S010-FR-007 | Functional | P1 | CI lint gate | Implemented |
| S010-FR-008 | Functional | P1 | CI test gate | Implemented |
| S010-FR-009 | Functional | P1 | CI self-check gate | Implemented |
| S010-FR-010 | Functional | P1 | CI artifact publication | Implemented |
| S010-FR-011 | Functional | P1 | Box PHAR compilation | Implemented |
| S010-FR-012 | Functional | P1 | PHAR distribution mechanism | Implemented |
| S010-FR-013 | Functional | P1 | Release version consistency | Implemented |
| S010-FR-014 | Functional | P1 | Laravel Pint integration | Implemented |
| S010-FR-015 | Functional | P1 | Release version bumping | Implemented |
| S010-FR-016 | Functional | P1 | Curated changelog promotion | Implemented |
| S010-FR-017 | Functional | P1 | Atomic git tagging | Implemented |
| S010-FR-018 | Functional | P1 | Gated GitHub Release creation | Implemented |
| S010-FR-019 | Functional | P1 | Sample project matrix | Implemented |
| S010-AS-001 | Acceptance | P1 | Full test suite passes locally | Implemented |
| S010-AS-002 | Acceptance | P1 | CI pipeline runs on branch push | Implemented |
| S010-AS-003 | Acceptance | P1 | CI pipeline runs on pull requests | Implemented |
| S010-AS-004 | Acceptance | P1 | CI lint gate fails on style violations | Implemented |
| S010-AS-005 | Acceptance | P1 | CI test gate fails on test failures | Implemented |
| S010-AS-006 | Acceptance | P1 | PHAR compiles successfully | Implemented |
| S010-AS-007 | Acceptance | P1 | PHAR runs Parity check successfully | Implemented |
| S010-AS-008 | Acceptance | P1 | Release script aligns VERSION and changelog | Implemented |
| S010-AS-009 | Acceptance | P1 | Release script atomically pushes commit and tag | Implemented |
| S010-AS-010 | Acceptance | P1 | Packagist smoke gates GitHub Release | Implemented |
| S010-AS-011 | Acceptance | P1 | Sample project configs pass | Implemented |
| S010-AS-012 | Acceptance | P1 | Release metadata mismatch is rejected | Implemented |
| S010-EC-001 | Edge Case | P1 | Test lacks a traceability comment | Documented |
| S010-EC-002 | Edge Case | P1 | Coverage below threshold triggers CI failure | Implemented |
| S010-EC-003 | Edge Case | P1 | Box PHAR compilation failure | Implemented |
| S010-EC-004 | Edge Case | P2 | Pint detects a CI style violation | Implemented |
| S010-EC-005 | Edge Case | P1 | Release script run on dirty git state | Implemented |
| S010-EC-006 | Edge Case | P2 | Composer dependencies unavailable during PHAR build | Implemented |
| S010-EC-007 | Edge Case | P1 | Duplicate release tag | Implemented |
| S010-EC-008 | Edge Case | P1 | GitHub Release asset upload failure | Implemented |
| S010-EC-009 | Edge Case | P1 | CI runs on a fork | Implemented |
| S010-EC-010 | Edge Case | P1 | PHPUnit XML coverage is unavailable | Implemented |
| S010-EC-011 | Edge Case | P1 | Release attempted outside main | Implemented |
| S010-NF-001 | Non-Functional | P1 | Test suite executes in under 60 seconds | Verified |
| S010-NF-002 | Non-Functional | P1 | CI pipeline completes in under 5 minutes | Verified |
| S010-NF-003 | Non-Functional | P2 | PHAR binary under 30MiB target | Verified |
| S010-NF-004 | Non-Functional | P2 | Main remains Pint-clean | Verified |
| S010-NF-005 | Non-Functional | P2 | PHPStan when configured | Not configured |
| S010-SC-001 | Success | P1 | Covered Pest suite passes | Verified |
| S010-SC-002 | Success | P1 | All CI gates green on main | Verified |
| S010-SC-003 | Success | P1 | PHAR binary is executable and self-contained | Verified |
| S010-SC-004 | Success | P1 | Release assets are checksummed and attached | Implemented |
| S010-SC-005 | Success | P1 | Samples cover supported stacks | Verified |
| S010-SC-006 | Success | P1 | Version identity is consistent across channels | Implemented |
| S010-SC-007 | Success | P1 | Changelog contains curated release notes | Verified |
| S010-SC-008 | Success | P1 | Exact Packagist release passes check and test | Implemented |

## Cross-Spec Dependencies

- **Depends on:** S001 (CLI Commands -- `parity check` and `parity init` are exercised by the test suite and CI), S002 (Rules System -- rule implementations are unit-tested), S003 (Coverage Readers -- coverage readers are tested with fixture files), S004 (Coverage Linkers -- linker implementations have dedicated Pest tests), S005 (Plugin System -- plugin loading is exercised in integration tests)
- **Required by:** N/A (this is a foundational infrastructure spec)
