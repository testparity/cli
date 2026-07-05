# S004: Coverage Linkers

| Field | Value |
|-------|-------|
| Spec | S004 |
| Feature | Coverage Linkers |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Coverage Linkers are the static analysis components responsible for determining whether a PHP test file declares what source code it covers. They work entirely through regex-based analysis of file content -- no PHP execution, no AST parsing, no autoloading required. This is a core differentiator of Parity's approach: coverage declaration validation at static analysis speed.

The module provides two concrete linkers -- `PestCoversLinker` for Pest's `covers()` / `->covers()` syntax and `PhpAttributeLinker` for PHPUnit's `#[CoversClass()]` PHP 8 attribute -- unified behind a common `CoverageLinkerInterface`. A `CoverageLinkerRegistry` orchestrates linker selection, supporting explicit configuration (pick one or both) and an `auto` mode that tries all registered linkers in order.

The `enforce-coverage-link` rule (specified in S002) is the primary consumer. It delegates to the registry to verify that a test file's coverage declarations match the expected source class FQCN. This spec covers the linker interface contract, both concrete implementations, the registry's selection strategy, class reference resolution (use maps, namespaces, aliased imports, FQCN), and the configuration surface.

## User Scenarios

S004-US-001 [P1] As a developer using Pest, I want Parity to detect my `covers()` and `->covers()` declarations so that I can validate coverage links without running tests.

S004-US-002 [P1] As a developer using PHPUnit, I want Parity to detect my `#[CoversClass()]` attributes so that I can enforce coverage declarations statically.

S004-US-003 [P1] As a developer on a mixed project (Pest + PHPUnit), I want Parity to auto-detect the correct linker per test file so that I do not need separate configuration for each testing framework.

S004-US-004 [P2] As a developer, I want to configure which linkers apply to each structure block so that I can limit detection to only the syntax my project uses.

S004-US-005 [P2] As a developer using a custom attribute (not the default PHPUnit `CoversClass`), I want to configure the attribute FQCN so that Parity detects my project-specific attribute.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S004-FR-001 | Functional | P1 | CoverageLinkerInterface contract | Draft |
| S004-FR-002 | Functional | P1 | PestCoversLinker supports() detection | Draft |
| S004-FR-003 | Functional | P1 | PestCoversLinker extraction patterns | Draft |
| S004-FR-004 | Functional | P1 | PhpAttributeLinker supports() detection | Draft |
| S004-FR-005 | Functional | P1 | PhpAttributeLinker extraction patterns | Draft |
| S004-FR-006 | Functional | P1 | Class reference resolution | Draft |
| S004-FR-007 | Functional | P1 | CoverageLinkerRegistry auto-detection | Draft |
| S004-FR-008 | Functional | P1 | Registry fromConfig() factory | Draft |
| S004-FR-009 | Functional | P2 | Custom attribute FQCN configuration | Draft |
| S004-FR-010 | Functional | P1 | Static analysis only (no execution) | Draft |
| S004-FR-011 | Functional | P1 | Deduplication of extracted classes | Draft |
| S004-FR-012 | Functional | P1 | PhpAttributeLinker only reads pre-class attributes | Draft |
| S004-IF-001 | Interface | P1 | CoverageLinkerInterface method signatures | Draft |
| S004-IF-002 | Interface | P1 | CoverageLinkerRegistry public API | Draft |
| S004-IF-003 | Interface | P1 | resolveClassReference static API | Draft |
| S004-IF-004 | Interface | P2 | Registry fromConfig() config mapping | Draft |
| S004-AS-001 | Acceptance | P1 | Pest chained covers() detected | Draft |
| S004-AS-002 | Acceptance | P1 | Pest file-level covers() detected | Draft |
| S004-AS-003 | Acceptance | P1 | PHPUnit CoversClass attribute detected | Draft |
| S004-AS-004 | Acceptance | P1 | Auto mode selects correct linker | Draft |
| S004-AS-005 | Acceptance | P1 | Multiple classes extracted | Draft |
| S004-AS-006 | Acceptance | P2 | Custom attribute FQCN works | Draft |
| S004-AS-007 | Acceptance | P1 | Validation rejects wrong FQCN | Draft |
| S004-AS-008 | Acceptance | P1 | No linker matches returns empty | Draft |
| S004-EC-001 | Edge Case | P1 | Class keyword in string literal | Draft |
| S004-EC-002 | Edge Case | P1 | Leading backslash FQCN | Draft |
| S004-EC-003 | Edge Case | P1 | Aliased import (use ... as ...) | Draft |
| S004-EC-004 | Edge Case | P1 | Sub-namespace in class reference | Draft |
| S004-EC-005 | Edge Case | P1 | No use map and no namespace | Draft |
| S004-EC-006 | Edge Case | P2 | Empty test file | Draft |
| S004-EC-007 | Edge Case | P2 | covers() with string literal argument | Draft |
| S004-EC-008 | Edge Case | P1 | Attribute inside class body ignored | Draft |
| S004-EC-009 | Edge Case | P2 | Unknown linker name in config | Draft |
| S004-EC-010 | Edge Case | P1 | File with both class declaration and Pest calls | Draft |
| S004-EC-011 | Edge Case | P2 | Duplicate covers of same class | Draft |
| S004-EC-012 | Edge Case | P1 | Non-CoversClass attributes ignored | Draft |
| S004-EC-013 | Edge Case | P2 | Unresolvable argument format | Draft |
| S004-EC-014 | Edge Case | P2 | Unbalanced brackets in attribute | Draft |
| S004-SC-001 | Success | P1 | All Pest covers() variants detected | Draft |
| S004-SC-002 | Success | P1 | All PHPUnit CoversClass variants detected | Draft |
| S004-SC-003 | Success | P1 | Auto-detection selects correct linker | Draft |
| S004-SC-004 | Success | P1 | Zero false positives on non-coverage syntax | Draft |
| S004-SC-005 | Success | P1 | Class resolution handles all import styles | Draft |

## Cross-Spec Dependencies

- **Depends on:** None (standalone module)
- **Required by:** S002 (Rules System -- `enforce-coverage-link` rule consumes the linker registry), S006 (Configuration -- `linkers` parameter per structure block)
