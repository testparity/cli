# S003: Coverage Readers

| Field | Value |
|-------|-------|
| Spec | S003 |
| Feature | Coverage Readers |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

The Coverage Readers module is responsible for ingesting pre-generated XML coverage reports and normalizing them into a uniform data structure that downstream rules and the CLI can consume. Parity never executes tests; it reads existing coverage files produced by language-native test and coverage tools.

Three coverage formats are supported: **Clover XML** (a single file with per-file statement coverage), **Cobertura XML** (a portable single-file format used by many ecosystems), and **PHPUnit XML** (a directory of per-class XML files with per-line, per-test attribution). The format is detected automatically based on whether the configured path points to a file or a directory containing `index.xml`. When multiple paths are configured, the first existing match wins.

The data produced by these readers feeds directly into the rules system (S002). Clover and Cobertura XML provide enough data for the `minimum-coverage` rule. PHPUnit XML additionally enables `matched-coverage`, `coverage-attribution`, and the `--show-tests` CLI flag (S001) by exposing which specific test methods covered each line of each source file.

## User Scenarios

S003-US-001 [P1] As a developer running `parity check`, I want per-file coverage percentages read from my Clover XML report so that the `minimum-coverage` rule can evaluate each source file.

S003-US-002 [P1] As a developer using PHPUnit XML coverage, I want per-test attribution data so that `matched-coverage` can distinguish coverage from the matching test vs. incidental coverage from other tests.

S003-US-003 [P2] As a CI pipeline operator, I want Parity to auto-detect the coverage format from the configured path so that I do not need to specify the format explicitly.

S003-US-004 [P2] As a developer, I want to configure multiple fallback coverage paths so that PHPUnit XML is used when available and Clover XML is used as a fallback.

S003-US-006 [P1] As a developer in a non-PHP ecosystem, I want to use Cobertura XML so that Parity can enforce per-file coverage for JavaScript, TypeScript, Rust, Python, Go, and other projects that commonly emit Cobertura.

S003-US-005 [P1] As a developer, I want clear error messages when coverage files are missing or malformed so that I can diagnose CI pipeline issues quickly.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S003-FR-001 | Functional | P1 | Clover XML per-file coverage reading | Draft |
| S003-FR-002 | Functional | P1 | Clover XML global coverage reading | Draft |
| S003-FR-003 | Functional | P1 | Path normalization with project root | Draft |
| S003-FR-004 | Functional | P1 | PHPUnit XML directory reading | Draft |
| S003-FR-005 | Functional | P1 | PHPUnit XML per-file coverage | Draft |
| S003-FR-006 | Functional | P1 | PHPUnit XML per-test attribution | Draft |
| S003-FR-007 | Functional | P1 | PHPUnit XML per-line coverage mapping | Draft |
| S003-FR-008 | Functional | P1 | PHPUnit XML executable line counts | Draft |
| S003-FR-009 | Functional | P1 | PHPUnit XML global coverage percentage | Draft |
| S003-FR-010 | Functional | P1 | Format auto-detection | Draft |
| S003-FR-011 | Functional | P1 | Multi-path fallback resolution | Draft |
| S003-FR-012 | Functional | P1 | Error handling for missing files | Draft |
| S003-FR-013 | Functional | P1 | Error handling for malformed XML | Draft |
| S003-FR-014 | Functional | P2 | Source prefix extraction from PHPUnit XML | Draft |
| S003-FR-015 | Functional | P2 | Dual-key lookup (absolute + relative) | Draft |
| S003-FR-016 | Functional | P1 | Zero-statement files default to 100% | Draft |
| S003-FR-017 | Functional | P1 | Cobertura XML per-file coverage reading | Draft |
| S003-FR-018 | Functional | P1 | Cobertura XML global coverage reading | Draft |
| S003-IF-001 | Interface | P1 | CoverageReader.read() signature and return type | Draft |
| S003-IF-002 | Interface | P1 | CoverageReader.readGlobalCoverage() signature | Draft |
| S003-IF-003 | Interface | P1 | PhpUnitXmlCoverageReader.read() signature and return type | Draft |
| S003-IF-004 | Interface | P1 | Clover XML schema contract | Draft |
| S003-IF-005 | Interface | P1 | PHPUnit XML schema contract | Draft |
| S003-IF-006 | Interface | P1 | Normalized output consumed by CheckCommand | Draft |
| S003-AS-001 | Acceptance | P1 | Read Clover XML happy path | Draft |
| S003-AS-002 | Acceptance | P1 | Read PHPUnit XML happy path | Draft |
| S003-AS-003 | Acceptance | P1 | Format auto-detection selects correct reader | Draft |
| S003-AS-004 | Acceptance | P1 | Multi-path fallback selects first existing | Draft |
| S003-AS-005 | Acceptance | P1 | Per-test attribution populates testsByFile | Draft |
| S003-AS-006 | Acceptance | P2 | Source prefix prepended to relative paths | Draft |
| S003-AS-007 | Acceptance | P1 | Missing coverage file returns empty/null | Draft |
| S003-AS-008 | Acceptance | P1 | Malformed XML returns empty/null | Draft |
| S003-AS-009 | Acceptance | P1 | Global coverage from Clover project metrics | Draft |
| S003-AS-010 | Acceptance | P1 | Global coverage from PHPUnit XML root directory | Draft |
| S003-AS-011 | Acceptance | P1 | Read Cobertura XML happy path | Draft |
| S003-EC-001 | Edge Case | P1 | Coverage file does not exist | Draft |
| S003-EC-002 | Edge Case | P1 | Coverage file is not readable (permissions) | Draft |
| S003-EC-003 | Edge Case | P1 | XML is malformed / not valid XML | Draft |
| S003-EC-004 | Edge Case | P1 | Clover file element with no metrics child | Draft |
| S003-EC-005 | Edge Case | P1 | Clover file element with empty name attribute | Draft |
| S003-EC-006 | Edge Case | P1 | Zero statements in a file | Draft |
| S003-EC-007 | Edge Case | P1 | PHPUnit XML directory missing index.xml | Draft |
| S003-EC-008 | Edge Case | P1 | PHPUnit XML file node with empty href | Draft |
| S003-EC-009 | Edge Case | P2 | PHPUnit XML referenced file does not exist on disk | Draft |
| S003-EC-010 | Edge Case | P2 | PHPUnit XML coverage line with no nr attribute | Draft |
| S003-EC-011 | Edge Case | P2 | PHPUnit XML covered element with empty by attribute | Draft |
| S003-EC-012 | Edge Case | P2 | No coverage candidates found at any configured path | Draft |
| S003-EC-013 | Edge Case | P2 | Windows-style backslash paths in XML | Draft |
| S003-EC-014 | Edge Case | P2 | Relative paths in Clover XML without project root | Draft |
| S003-EC-015 | Edge Case | P2 | Project source attribute missing from PHPUnit XML | Draft |
| S003-EC-016 | Edge Case | P1 | All configured paths are missing | Draft |
| S003-SC-001 | Success | P1 | Clover reader produces correct per-file percentages | Draft |
| S003-SC-002 | Success | P1 | PHPUnit XML reader produces correct per-file percentages | Draft |
| S003-SC-003 | Success | P1 | PHPUnit XML reader produces correct per-test attribution | Draft |
| S003-SC-004 | Success | P1 | Format detection is deterministic and correct | Draft |
| S003-SC-005 | Success | P1 | No exceptions propagated to CLI on bad input | Draft |
| S003-SC-006 | Success | P1 | Cobertura reader produces correct per-file and global percentages | Draft |

## Cross-Spec Dependencies

- **Depends on:** S006 (Configuration -- `coverage_xml` path list, `min_coverage_global`)
- **Required by:** S002 (Rules System -- `minimum-coverage`, `matched-coverage`, `coverage-attribution` rules consume the coverage data structures produced here)
- **Required by:** S001 (CLI Commands -- `--show-tests` flag relies on `testsByFile` from PHPUnit XML reader)
- **Required by:** S004 (Coverage Linkers -- consumes per-line coverage data for linking)
