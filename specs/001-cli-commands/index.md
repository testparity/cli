# S001: CLI Commands & Interface

| Field | Value |
|-------|-------|
| Spec | S001 |
| Feature | CLI Commands & Interface |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Parity exposes two CLI commands -- `parity check` and `parity init` -- that form the entire user-facing interface of the tool. The `check` command is the core analysis command: it loads configuration, discovers source files, evaluates pluggable rules against each file, and produces either a human-readable table or machine-readable JSON report. The `init` command is a one-time setup helper that writes a default `parity.yaml` configuration file.

This specification defines the complete contract for both commands: their signatures, flags, exit codes, output formats, error messages, and behavioral edge cases. It serves as the source of truth for implementation (S001-FR), acceptance testing (S001-AS), QA edge-case coverage (S001-EC), interface contracts (S001-IF), and measurable success criteria (S001-SC).

The CLI is built on Laravel Zero (Symfony Console underneath). It reads configuration from `parity.yaml` (specified by S006), evaluates rules from the rule registry (S002), loads coverage data via readers (S003), resolves file paths via namespace mapping (S007), and formats output per S008.

## User Scenarios

S001-US-001 [P1] As a developer, I want to run `parity check` from my project root so that I can see which source files are missing tests, lack coverage attribution, or fall below coverage thresholds.

S001-US-002 [P1] As a CI pipeline, I want `parity check` to return exit code 0 on success and 1 on failure so that I can gate merges on structural parity.

S001-US-003 [P1] As a CI pipeline, I want `parity check --format=json` to produce structured JSON output so that I can parse results programmatically.

S001-US-004 [P2] As a developer, I want `parity check --show-tests` to list the specific test methods covering each file so that I can verify coverage attribution at a glance.

S001-US-005 [P1] As a new user, I want `parity init` to create a sensible default `parity.yaml` so that I can get started without reading documentation first.

S001-US-006 [P2] As a developer working in a monorepo, I want `parity check --config=path/to/parity.yaml` so that I can point Parity at a config file outside my current working directory.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S001-FR-001 | Functional | P1 | `check` command signature | Draft |
| S001-FR-002 | Functional | P1 | Config file resolution | Draft |
| S001-FR-003 | Functional | P1 | `--config` flag behavior | Draft |
| S001-FR-004 | Functional | P1 | `--format` flag behavior | Draft |
| S001-FR-005 | Functional | P2 | `--show-tests` flag behavior | Draft |
| S001-FR-006 | Functional | P1 | Exit code semantics | Draft |
| S001-FR-007 | Functional | P1 | Table output format | Draft |
| S001-FR-008 | Functional | P1 | JSON output format | Draft |
| S001-FR-009 | Functional | P1 | Structure iteration and rule evaluation | Draft |
| S001-FR-010 | Functional | P1 | Global coverage reporting | Draft |
| S001-FR-011 | Functional | P1 | Coverage summary table | Draft |
| S001-FR-012 | Functional | P2 | Unmatched test warning | Draft |
| S001-FR-013 | Functional | P1 | `init` command signature | Draft |
| S001-FR-014 | Functional | P1 | `init` creates default config | Draft |
| S001-FR-015 | Functional | P1 | `init` idempotency guard | Draft |
| S001-FR-016 | Functional | P1 | Plugin loading and warnings | Draft |
| S001-FR-017 | Functional | P1 | Coverage data loading | Draft |
| S001-FR-018 | Functional | P2 | Legacy config format support | Draft |
| S001-IF-001 | Interface | P1 | `check` command flag contract | Draft |
| S001-IF-002 | Interface | P1 | Exit code contract | Draft |
| S001-IF-003 | Interface | P1 | JSON output schema | Draft |
| S001-IF-004 | Interface | P1 | Table output structure | Draft |
| S001-IF-005 | Interface | P1 | `init` command contract | Draft |
| S001-AS-001 | Acceptance | P1 | Happy path: all checks pass | Draft |
| S001-AS-002 | Acceptance | P1 | Violations found: exit code 1 | Draft |
| S001-AS-003 | Acceptance | P1 | JSON output mode | Draft |
| S001-AS-004 | Acceptance | P2 | Show tests flag | Draft |
| S001-AS-005 | Acceptance | P1 | Custom config path | Draft |
| S001-AS-006 | Acceptance | P1 | Init creates config | Draft |
| S001-AS-007 | Acceptance | P1 | Init with existing config | Draft |
| S001-AS-008 | Acceptance | P1 | Missing config file | Draft |
| S001-AS-009 | Acceptance | P1 | Missing coverage file | Draft |
| S001-AS-010 | Acceptance | P1 | Empty structure list | Draft |
| S001-AS-011 | Acceptance | P2 | Global coverage below threshold | Draft |
| S001-AS-012 | Acceptance | P1 | Multiple structures in one run | Draft |
| S001-EC-001 | Edge Case | P1 | Config file not found | Draft |
| S001-EC-002 | Edge Case | P1 | Config file unreadable | Draft |
| S001-EC-003 | Edge Case | P1 | Invalid YAML syntax | Draft |
| S001-EC-004 | Edge Case | P1 | No coverage file found | Draft |
| S001-EC-005 | Edge Case | P1 | Empty structure array | Draft |
| S001-EC-006 | Edge Case | P2 | Source directory does not exist | Draft |
| S001-EC-007 | Edge Case | P2 | No PHP files in source directory | Draft |
| S001-EC-008 | Edge Case | P1 | `--config` points to nonexistent file | Draft |
| S001-EC-009 | Edge Case | P2 | `getcwd()` returns false | Draft |
| S001-EC-010 | Edge Case | P2 | `--format` with invalid value | Draft |
| S001-EC-011 | Edge Case | P1 | `init` when `getcwd()` fails | Draft |
| S001-EC-012 | Edge Case | P2 | `init` write permission denied | Draft |
| S001-EC-013 | Edge Case | P2 | Plugin warnings suppressed in JSON mode | Draft |
| S001-EC-014 | Edge Case | P2 | Symfony/Yaml not installed | Draft |
| S001-SC-001 | Success | P1 | Exit codes are deterministic | Draft |
| S001-SC-002 | Success | P1 | JSON output is valid JSON | Draft |
| S001-SC-003 | Success | P1 | Table output grouped by directory | Draft |
| S001-SC-004 | Success | P1 | Zero violations = exit 0 | Draft |
| S001-SC-005 | Success | P1 | Any enforced violation = exit 1 | Draft |

## Cross-Spec Dependencies

- **Depends on:** S002 (Rules System -- rule evaluation and registry), S003 (Coverage Readers -- coverage data loading), S006 (Configuration & Settings -- `parity.yaml` parsing and `Settings` object), S007 (Path & Namespace Mapping -- source-to-test path resolution)
- **Required by:** S005 (Plugin System -- plugin warnings rendered by CLI), S008 (Output Formats -- CLI produces table and JSON output)
