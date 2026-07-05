# S008: Output Formats

| Field | Value |
|-------|-------|
| Spec | S008 |
| Feature | Output Formats |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Output Formats is the presentation layer of Parity. Every time `parity check` runs, the evaluation pipeline produces a collection of per-file `RuleResult` objects grouped by structure. The Output Formats module transforms that data into one of two representations: a human-readable console table (the default) or a machine-readable JSON document (selected via `--format=json`). It also governs exit code semantics, ANSI color handling, and the contract between what the tool displays and what it enforces.

The table format is designed for developer workstations: results are grouped by directory within each structure, columns are dynamically generated from active rules, pass/fail cells use color coding, and a coverage summary table follows at the end. The JSON format is designed for CI/CD pipelines and automated tooling: it emits a single, self-contained JSON document to stdout with no decorative output, enabling downstream parsing by scripts, dashboards, and PR comment bots.

Exit codes bridge both formats: exit 0 means all enforced rules passed and global coverage (if configured) meets its threshold; exit 1 means at least one enforced violation was found or a fatal error occurred (missing config, missing coverage data). Informational rules (such as `coverage-attribution`) appear in output but never influence the exit code. Color output uses Symfony Console ANSI tags in table mode and is absent in JSON mode; TTY detection governs whether ANSI codes reach the terminal.

## User Scenarios

S008-US-001 [P1] As a developer running Parity locally, I want a color-coded table showing pass/fail per rule per file so that I can quickly spot structural gaps without reading raw data.

S008-US-002 [P1] As a CI pipeline, I want `--format=json` to produce a single parseable JSON document so that I can programmatically extract results for PR comments, dashboards, or artifact storage.

S008-US-003 [P1] As a CI pipeline, I want exit code 0 on success and exit code 1 on failure so that I can gate merges on structural parity.

S008-US-004 [P2] As a developer using `--show-tests`, I want individual test method names listed in the table so that I can verify coverage attribution at a glance.

S008-US-005 [P2] As a developer piping output to a file, I want ANSI color codes to be automatically suppressed so that the file contains clean text.

S008-US-006 [P1] As a developer, I want informational rules to appear in the table for visibility without causing the check to fail, so that I can see coverage attribution without false alarms.

S008-US-007 [P1] As a developer with multiple structure blocks, I want one table per structure followed by a single combined summary so that I get a comprehensive view of the entire project.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S008-FR-001 | Functional | P1 | Table format as default output | Draft |
| S008-FR-002 | Functional | P1 | Dynamic column generation from active rules | Draft |
| S008-FR-003 | Functional | P1 | Directory grouping with separator rows | Draft |
| S008-FR-004 | Functional | P1 | Alphabetical sort within each structure | Draft |
| S008-FR-005 | Functional | P1 | Pass/fail color coding in table cells | Draft |
| S008-FR-006 | Functional | P1 | OK column aggregation logic | Draft |
| S008-FR-007 | Functional | P1 | Structure title and path subtitles | Draft |
| S008-FR-008 | Functional | P1 | Coverage summary table after all structures | Draft |
| S008-FR-009 | Functional | P1 | JSON format via --format=json | Draft |
| S008-FR-010 | Functional | P1 | JSON top-level schema | Draft |
| S008-FR-011 | Functional | P1 | JSON per-file rule results | Draft |
| S008-FR-012 | Functional | P1 | JSON encoding flags | Draft |
| S008-FR-013 | Functional | P1 | JSON output purity (no non-JSON text) | Draft |
| S008-FR-014 | Functional | P1 | Exit code 0 on all-pass | Draft |
| S008-FR-015 | Functional | P1 | Exit code 1 on enforced violation | Draft |
| S008-FR-016 | Functional | P1 | Exit code 1 on fatal error | Draft |
| S008-FR-017 | Functional | P1 | Exit code parity across formats | Draft |
| S008-FR-018 | Functional | P1 | Informational rules excluded from exit code | Draft |
| S008-FR-019 | Functional | P1 | ANSI color in table mode | Draft |
| S008-FR-020 | Functional | P2 | TTY detection for color suppression | Draft |
| S008-FR-021 | Functional | P1 | No ANSI color in JSON mode | Draft |
| S008-FR-022 | Functional | P2 | --show-tests flag behavior | Draft |
| S008-FR-023 | Functional | P1 | CoverageAttributionRule dual-column rendering | Draft |
| S008-FR-024 | Functional | P2 | Global coverage message in table mode | Draft |
| S008-FR-025 | Functional | P2 | Unmatched test warnings in table mode | Draft |
| S008-FR-026 | Functional | P1 | Plugin warnings suppressed in JSON mode | Draft |
| S008-IF-001 | Interface | P1 | JSON output schema (full specification) | Draft |
| S008-IF-002 | Interface | P1 | Table column contract | Draft |
| S008-IF-003 | Interface | P1 | Exit code contract | Draft |
| S008-IF-004 | Interface | P1 | Coverage summary table schema | Draft |
| S008-IF-005 | Interface | P1 | Rule-to-column mapping contract | Draft |
| S008-AS-001 | Acceptance | P1 | Table mode: all rules pass | Draft |
| S008-AS-002 | Acceptance | P1 | Table mode: mixed pass/fail | Draft |
| S008-AS-003 | Acceptance | P1 | JSON mode: all rules pass | Draft |
| S008-AS-004 | Acceptance | P1 | JSON mode: violations present | Draft |
| S008-AS-005 | Acceptance | P1 | Exit code 0 on clean run | Draft |
| S008-AS-006 | Acceptance | P1 | Exit code 1 on enforced failure | Draft |
| S008-AS-007 | Acceptance | P1 | Informational rule does not cause exit 1 | Draft |
| S008-AS-008 | Acceptance | P2 | --show-tests in table mode | Draft |
| S008-AS-009 | Acceptance | P1 | Multiple structures produce multiple tables | Draft |
| S008-AS-010 | Acceptance | P1 | JSON output parseable by jq | Draft |
| S008-AS-011 | Acceptance | P1 | Global coverage below threshold in JSON | Draft |
| S008-AS-012 | Acceptance | P2 | Piped output has no ANSI codes | Draft |
| S008-EC-001 | Edge Case | P1 | Empty project (no source files in any structure) | Draft |
| S008-EC-002 | Edge Case | P1 | All test files missing | Draft |
| S008-EC-003 | Edge Case | P1 | Zero enforced rules active | Draft |
| S008-EC-004 | Edge Case | P2 | Single file with no subdirectory | Draft |
| S008-EC-005 | Edge Case | P2 | Rule returns null columnHeader | Draft |
| S008-EC-006 | Edge Case | P1 | All rules pass but global coverage below threshold | Draft |
| S008-EC-007 | Edge Case | P2 | Structure source directory does not exist | Draft |
| S008-EC-008 | Edge Case | P1 | JSON with zero structures | Draft |
| S008-EC-009 | Edge Case | P2 | Very long file paths in table | Draft |
| S008-EC-010 | Edge Case | P1 | Only informational rules configured | Draft |
| S008-EC-011 | Edge Case | P2 | Unicode characters in file paths | Draft |
| S008-EC-012 | Edge Case | P1 | Global coverage is null (not configured) | Draft |
| S008-EC-013 | Edge Case | P2 | Coverage percent exactly at threshold boundary | Draft |
| S008-EC-014 | Edge Case | P2 | --format with invalid value | Draft |
| S008-SC-001 | Success | P1 | JSON output is always valid JSON | Draft |
| S008-SC-002 | Success | P1 | Exit codes are deterministic | Draft |
| S008-SC-003 | Success | P1 | Table rows grouped by directory | Draft |
| S008-SC-004 | Success | P1 | Informational rules never affect exit code | Draft |
| S008-SC-005 | Success | P1 | Format flag has no effect on exit code logic | Draft |
| S008-SC-006 | Success | P1 | JSON contains all evaluated rule results | Draft |

## Cross-Spec Dependencies

- **Depends on:** S001 (CLI Commands -- `--format` flag, `--show-tests` flag, exit code constants), S002 (Rules System -- `RuleInterface::isEnforced()`, `RuleInterface::columnHeader()`, `RuleInterface::formatCell()`, `RuleResult` value object), S003 (Coverage Readers -- coverage data that feeds per-file and global coverage values), S006 (Configuration -- `min_coverage_global`, structure entries, `min_coverage` defaults)
- **Required by:** S001 (CLI Commands -- output rendering is delegated to this module's contracts)
