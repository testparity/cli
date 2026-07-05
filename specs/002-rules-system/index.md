# S002: Rules System

| Field | Value |
|-------|-------|
| Spec | S002 |
| Feature | Rules System |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

The Rules System is the core evaluation engine of Parity. It provides a pluggable architecture where each rule is an independent unit that receives an immutable context about a source file and its corresponding test, evaluates a single concern, and returns a pass/fail result. Rules are registered in a central registry, resolved from YAML configuration, and evaluated per-file during the `check` command.

The system distinguishes between **enforced** rules (whose failures cause the overall check to fail) and **informational** rules (which display data but never cause failures). Each rule declares its own parameters, validates them via the registry, and controls how its results appear in output tables. This design allows both built-in rules and third-party plugin rules (see S005) to participate identically in the evaluation pipeline.

Five built-in rules ship with Parity: `test-exists` (auto-active), `enforce-coverage-link`, `minimum-coverage`, `matched-coverage`, and `coverage-attribution`. The Rules System depends on coverage data from S003 (Coverage Readers), coverage linking from S004 (Coverage Linkers), configuration from S006, and path mapping from S007. Custom rules extend the system via S005 (Plugin System).

## User Scenarios

S002-US-001 [P1] As a developer, I want Parity to check whether each source file has a matching test file so that I can ensure structural parity across my codebase.

S002-US-002 [P1] As a team lead, I want to enforce minimum per-file coverage thresholds so that no source file falls below the agreed standard.

S002-US-003 [P1] As a developer using Pest or PHPUnit, I want Parity to verify that each test file declares which class it covers so that coverage attribution is explicit.

S002-US-004 [P2] As a developer with PHPUnit XML coverage, I want to see how much coverage comes from the matching test file alone so that I can identify files relying on incidental coverage.

S002-US-005 [P2] As a developer, I want informational rules that show coverage attribution without failing the check so that I have visibility into test distribution.

S002-US-006 [P2] As a plugin author, I want a clear interface contract so that I can write custom rules that integrate seamlessly with Parity's evaluation pipeline.

S002-US-007 [P1] As a developer, I want to configure rule parameters per structure block in `parity.yaml` so that different parts of my codebase can have different thresholds.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S002-FR-001 | Functional | P1 | RuleInterface contract | Draft |
| S002-FR-002 | Functional | P1 | RuleResult value object | Draft |
| S002-FR-003 | Functional | P1 | RuleContext immutable data carrier | Draft |
| S002-FR-004 | Functional | P1 | RuleRegistry registration and lookup | Draft |
| S002-FR-005 | Functional | P1 | RuleRegistry configuration resolution | Draft |
| S002-FR-006 | Functional | P1 | Parameter validation | Draft |
| S002-FR-007 | Functional | P1 | test-exists rule | Draft |
| S002-FR-008 | Functional | P1 | enforce-coverage-link rule | Draft |
| S002-FR-009 | Functional | P1 | minimum-coverage rule | Draft |
| S002-FR-010 | Functional | P2 | matched-coverage rule | Draft |
| S002-FR-011 | Functional | P2 | coverage-attribution rule | Draft |
| S002-FR-012 | Functional | P1 | Enforcement vs informational distinction | Draft |
| S002-FR-013 | Functional | P1 | Auto-active test-exists rule | Draft |
| S002-FR-014 | Functional | P2 | Auto-appended PHPUnit XML rules | Draft |
| S002-FR-015 | Functional | P1 | Per-structure rule configuration | Draft |
| S002-FR-016 | Functional | P2 | Legacy configuration format support | Draft |
| S002-FR-017 | Functional | P1 | Table output formatting | Draft |
| S002-FR-018 | Functional | P1 | JSON output formatting | Draft |
| S002-IF-001 | Interface | P1 | RuleInterface method signatures | Draft |
| S002-IF-002 | Interface | P1 | RuleResult static constructors | Draft |
| S002-IF-003 | Interface | P1 | RuleContext constructor properties | Draft |
| S002-IF-004 | Interface | P1 | RuleRegistry public API | Draft |
| S002-IF-005 | Interface | P2 | YAML configuration schema | Draft |
| S002-AS-001 | Acceptance | P1 | Rule passes when test exists | Draft |
| S002-AS-002 | Acceptance | P1 | Rule fails when test missing | Draft |
| S002-AS-003 | Acceptance | P1 | Coverage link detected via pest-covers | Draft |
| S002-AS-004 | Acceptance | P1 | Coverage link detected via php-attribute | Draft |
| S002-AS-005 | Acceptance | P1 | Minimum coverage passes at threshold | Draft |
| S002-AS-006 | Acceptance | P1 | Minimum coverage fails below threshold | Draft |
| S002-AS-007 | Acceptance | P2 | Matched coverage computed from line data | Draft |
| S002-AS-008 | Acceptance | P2 | Coverage attribution shows test counts | Draft |
| S002-AS-009 | Acceptance | P1 | Unknown rule name throws exception | Draft |
| S002-AS-010 | Acceptance | P1 | Invalid parameters throw exception | Draft |
| S002-AS-011 | Acceptance | P1 | test-exists auto-prepended | Draft |
| S002-AS-012 | Acceptance | P2 | PHPUnit XML rules auto-appended | Draft |
| S002-AS-013 | Acceptance | P1 | Enforced rule failure causes check failure | Draft |
| S002-AS-014 | Acceptance | P2 | Informational rule never causes failure | Draft |
| S002-AS-015 | Acceptance | P1 | Custom plugin rule evaluated like built-in | Draft |
| S002-SC-001 | Success | P1 | All built-in rules independently testable | Draft |
| S002-SC-002 | Success | P1 | Custom rule integration via RuleInterface | Draft |
| S002-SC-003 | Success | P1 | Parameter validation prevents misconfiguration | Draft |
| S002-SC-004 | Success | P1 | Enforced/informational distinction correct | Draft |
| S002-EC-001 | Edge Case | P1 | Zero coverage percent | Draft |
| S002-EC-002 | Edge Case | P1 | 100% coverage percent | Draft |
| S002-EC-003 | Edge Case | P1 | No test file with coverage rules | Draft |
| S002-EC-004 | Edge Case | P2 | Zero executable lines | Draft |
| S002-EC-005 | Edge Case | P1 | Missing required parameter | Draft |
| S002-EC-006 | Edge Case | P2 | Empty rules array in config | Draft |
| S002-EC-007 | Edge Case | P2 | Duplicate rule in config | Draft |
| S002-EC-008 | Edge Case | P2 | Non-numeric coverage parameter | Draft |
| S002-EC-009 | Edge Case | P2 | Coverage percent at exact threshold boundary | Draft |
| S002-EC-010 | Edge Case | P2 | Test file exists but is empty/unreadable | Draft |
| S002-EC-011 | Edge Case | P2 | Rule name collision between built-in and plugin | Draft |
| S002-EC-012 | Edge Case | P2 | No line coverage data for matched-coverage | Draft |

## Cross-Spec Dependencies

- **Depends on:** S003 (Coverage Readers provide `coveragePercent`, `lineCoverage`, `coveringTests`), S004 (Coverage Linkers used by `enforce-coverage-link`), S006 (Configuration provides `min_coverage` defaults and structure rule arrays), S007 (Path & Namespace Mapping resolves test file paths and FQCNs)
- **Required by:** S001 (CLI check command evaluates rules), S005 (Plugin System extends via RuleInterface), S008 (Output Formats consume RuleResult and formatCell)
