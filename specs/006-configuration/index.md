# S006: Configuration & Settings

| Field | Value |
|-------|-------|
| Spec | S006 |
| Feature | Configuration & Settings |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Configuration is the foundation of Parity. Every behavior -- test naming conventions, which rules to apply, coverage thresholds, namespace resolution, and source-to-test directory mappings -- is driven by a single `parity.yaml` file. The configuration system has three core responsibilities: (1) discovering and loading the YAML file, (2) parsing it into a typed `Settings` DTO with sensible defaults, and (3) resolving per-structure rule configurations including backwards-compatible legacy format translation.

The `parity.yaml` schema defines two conceptual layers. The **global layer** (`settings`, `coverage_xml`, `min_coverage`, `min_coverage_global`, `min_matched_coverage`) establishes project-wide defaults for namespace mapping, file extensions, test naming, and coverage thresholds. The **structure layer** (`structure[]`) defines one or more source-to-test directory mappings, each with its own optional `rules` array and `file_map` overrides. This two-layer design lets teams enforce different standards for different parts of their codebase (e.g., stricter coverage for services than for controllers).

The system supports a legacy flat format (`source_path`/`test_path`, `enforce_attribute`, `enforce_coverage_link`) for backwards compatibility. When legacy keys are detected in a structure entry and no `rules` array is present, they are internally translated to the equivalent modern rules configuration. The `Settings` DTO is the canonical in-memory representation consumed by all downstream systems: the CLI (S001), rule evaluation (S002), coverage readers (S003), coverage linkers (S004), plugins (S005), and path mapping (S007).

## User Scenarios

S006-US-001 [P1] As a developer, I want Parity to read a `parity.yaml` file from my project root so that all analysis behavior is driven by declarative configuration.

S006-US-002 [P1] As a developer, I want to define namespace roots, test suffixes, and file extensions so that Parity correctly maps source files to test files regardless of my project's naming conventions.

S006-US-003 [P1] As a team lead, I want to set global coverage thresholds (`min_coverage`, `min_coverage_global`) so that every source file and the project overall meet minimum standards.

S006-US-004 [P1] As a developer, I want to define multiple structure blocks with per-structure rule overrides so that different parts of my codebase can have different testing and coverage requirements.

S006-US-005 [P2] As a developer with non-standard file naming, I want to use `file_map` within a structure to explicitly map source files to test files so that Parity does not flag them as missing.

S006-US-006 [P2] As a developer migrating from an older Parity version, I want the legacy config format (`source_path`/`test_path`/`enforce_attribute`) to still work so that I do not have to update my config immediately.

S006-US-007 [P1] As a new user, I want `parity init` to generate a sensible default `parity.yaml` so that I can start using Parity without manual configuration.

S006-US-008 [P1] As a CI engineer, I want to point Parity at a config file outside the current directory via `--config=path` so that I can use Parity in monorepo or non-standard directory layouts.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S006-FR-001 | Functional | P1 | Config file discovery | Draft |
| S006-FR-002 | Functional | P1 | YAML parsing | Draft |
| S006-FR-003 | Functional | P1 | Settings DTO construction | Draft |
| S006-FR-004 | Functional | P1 | Namespace roots configuration | Draft |
| S006-FR-005 | Functional | P1 | File extension and test naming configuration | Draft |
| S006-FR-006 | Functional | P1 | Coverage path resolution | Draft |
| S006-FR-007 | Functional | P1 | Global coverage thresholds | Draft |
| S006-FR-008 | Functional | P1 | Structure block format (modern) | Draft |
| S006-FR-009 | Functional | P1 | Structure rules configuration | Draft |
| S006-FR-010 | Functional | P2 | File map overrides | Draft |
| S006-FR-011 | Functional | P2 | Legacy structure format support | Draft |
| S006-FR-012 | Functional | P2 | Legacy rule translation | Draft |
| S006-FR-013 | Functional | P1 | Default values for all optional keys | Draft |
| S006-FR-014 | Functional | P1 | Init command default template | Draft |
| S006-FR-015 | Functional | P1 | Config path override via --config flag | Draft |
| S006-FR-016 | Functional | P1 | Project root derivation from config location | Draft |
| S006-FR-017 | Functional | P1 | test-exists auto-prepend | Draft |
| S006-FR-018 | Functional | P2 | PHPUnit XML rule auto-append | Draft |
| S006-FR-019 | Functional | P1 | Structure block iteration order | Draft |
| S006-FR-020 | Functional | P2 | Namespace separator configuration | Draft |
| S006-IF-001 | Interface | P1 | parity.yaml full schema | Draft |
| S006-IF-002 | Interface | P1 | Settings DTO constructor contract | Draft |
| S006-IF-003 | Interface | P1 | Settings::fromConfig() contract | Draft |
| S006-IF-004 | Interface | P1 | Structure block schema (modern) | Draft |
| S006-IF-005 | Interface | P2 | Structure block schema (legacy) | Draft |
| S006-IF-006 | Interface | P1 | Rule configuration formats | Draft |
| S006-IF-007 | Interface | P1 | Default template content | Draft |
| S006-AS-001 | Acceptance | P1 | Minimal valid config | Draft |
| S006-AS-002 | Acceptance | P1 | Full config with all keys | Draft |
| S006-AS-003 | Acceptance | P1 | Settings DTO defaults applied | Draft |
| S006-AS-004 | Acceptance | P1 | Multiple structures with different rules | Draft |
| S006-AS-005 | Acceptance | P2 | File map overrides standard naming | Draft |
| S006-AS-006 | Acceptance | P2 | Legacy format parsed correctly | Draft |
| S006-AS-007 | Acceptance | P1 | Coverage path as string | Draft |
| S006-AS-008 | Acceptance | P1 | Coverage path as array | Draft |
| S006-AS-009 | Acceptance | P1 | Init creates valid parseable config | Draft |
| S006-AS-010 | Acceptance | P1 | Custom config path resolves project root | Draft |
| S006-AS-011 | Acceptance | P1 | test-exists auto-prepended to rules | Draft |
| S006-AS-012 | Acceptance | P2 | Legacy enforce_attribute translated | Draft |
| S006-SC-001 | Success | P1 | All optional keys have defaults | Draft |
| S006-SC-002 | Success | P1 | Settings DTO is immutable | Draft |
| S006-SC-003 | Success | P1 | Round-trip: init output is parseable | Draft |
| S006-SC-004 | Success | P1 | Legacy configs produce identical Settings | Draft |
| S006-SC-005 | Success | P1 | Invalid config produces actionable errors | Draft |
| S006-EC-001 | Edge Case | P1 | Missing settings block entirely | Draft |
| S006-EC-002 | Edge Case | P1 | Empty parity.yaml (null parse result) | Draft |
| S006-EC-003 | Edge Case | P1 | coverage_xml as string vs array | Draft |
| S006-EC-004 | Edge Case | P2 | Missing namespace_roots | Draft |
| S006-EC-005 | Edge Case | P2 | Unknown keys in config (forward compatibility) | Draft |
| S006-EC-006 | Edge Case | P1 | Structure entry missing required paths | Draft |
| S006-EC-007 | Edge Case | P1 | Empty structure array | Draft |
| S006-EC-008 | Edge Case | P2 | file_map with empty or non-array value | Draft |
| S006-EC-009 | Edge Case | P1 | min_coverage as string vs number | Draft |
| S006-EC-010 | Edge Case | P2 | Negative or >100 coverage threshold | Draft |
| S006-EC-011 | Edge Case | P2 | Mixed legacy and modern keys in one structure | Draft |
| S006-EC-012 | Edge Case | P1 | Config file is valid YAML but not an array | Draft |
| S006-EC-013 | Edge Case | P2 | test_extension defaults to source_extension | Draft |
| S006-EC-014 | Edge Case | P2 | coverage_xml with non-string array elements | Draft |
| S006-EC-015 | Edge Case | P2 | Structure with rules but no test-exists | Draft |

## Cross-Spec Dependencies

- **Depends on:** None (configuration is a foundational module with no upstream spec dependencies)
- **Required by:** S001 (CLI Commands -- loads config, passes to Settings), S002 (Rules System -- reads per-structure rules, global min_coverage), S003 (Coverage Readers -- reads coverage_xml paths), S004 (Coverage Linkers -- reads linker selection from structure rules), S005 (Plugin System -- plugin rules referenced by name in structure rules), S007 (Path & Namespace Mapping -- reads namespace_roots, source_extension, test_suffix, test_extension, namespace_separator)
