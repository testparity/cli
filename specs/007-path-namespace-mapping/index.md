# S007: Path & Namespace Mapping

| Field | Value |
|-------|-------|
| Spec | S007 |
| Feature | Path & Namespace Mapping |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

Path & Namespace Mapping is the core resolution layer that connects PHP source files to their expected test files and converts file paths to fully qualified class names (FQCNs). Every other Parity module depends on this mapping being correct: the rules system needs accurate test paths to check existence, coverage linkers need accurate FQCNs to validate declarations, and the output layer needs both to produce meaningful reports.

The module has three responsibilities. First, the `NamespaceHelper` class converts between relative file paths and PHP FQCNs using configurable PSR-4 style `namespace_roots` mappings (e.g. `app/` maps to `App\`). Second, the source-to-test path derivation algorithm takes a source file path, strips the source directory prefix, appends a configurable test suffix, and places it under the corresponding test directory. Third, `file_map` overrides allow explicit source-to-test path mappings that bypass the algorithmic derivation entirely, supporting non-standard naming conventions.

Multiple structure blocks can coexist in a single `parity.yaml`, each defining independent source-to-test mappings with their own rules. A source file in `app/Http/Controllers/` might map to `tests/Feature/Controllers/` while `app/Services/` maps to `tests/Unit/Services/`. The mapping logic must handle overlapping prefixes, deeply nested subdirectories, platform-specific path separators, and edge cases like missing namespace roots or files at the root of a source directory.

## User Scenarios

S007-US-001 [P1] As a developer, I want Parity to automatically derive the expected test file path from any source file path so that I do not need to specify each mapping manually.

S007-US-002 [P1] As a developer, I want Parity to convert file paths to FQCNs so that coverage linkers can match `CoversClass` declarations against the correct source class.

S007-US-003 [P1] As a developer with non-standard test naming, I want to use `file_map` to override the default path derivation so that Parity can handle legacy or unusual file relationships.

S007-US-004 [P1] As a developer on a Laravel project, I want to configure multiple structure blocks (unit, feature, integration) so that different parts of my codebase map to different test directories.

S007-US-005 [P2] As a developer on a non-PHP project, I want to configure custom `namespace_separator`, `source_extension`, `test_suffix`, and `test_extension` so that Parity can work with other languages.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S007-FR-001 | Functional | P1 | pathToFqcn converts relative paths to FQCNs | Draft |
| S007-FR-002 | Functional | P1 | Namespace root matching is case-insensitive on first segment | Draft |
| S007-FR-003 | Functional | P1 | Default fallback when no namespace root matches | Draft |
| S007-FR-004 | Functional | P1 | pathToFqcn strips source extension before conversion | Draft |
| S007-FR-005 | Functional | P1 | pathToFqcn normalizes backslashes to forward slashes | Draft |
| S007-FR-006 | Functional | P1 | pathToFqcn strips leading slash from input | Draft |
| S007-FR-007 | Functional | P1 | sourcePathToTestPath derives test path from source path | Draft |
| S007-FR-008 | Functional | P1 | sourcePathToTestPath appends test suffix before extension | Draft |
| S007-FR-009 | Functional | P1 | sourcePathToTestPath preserves subdirectory structure | Draft |
| S007-FR-010 | Functional | P1 | sourcePathToTestPath handles source file not under source base | Draft |
| S007-FR-011 | Functional | P1 | file_map overrides bypass algorithmic derivation | Draft |
| S007-FR-012 | Functional | P1 | file_map paths are relative to structure source/test dirs | Draft |
| S007-FR-013 | Functional | P1 | Multiple structure blocks operate independently | Draft |
| S007-FR-014 | Functional | P1 | normalizeRelativePath produces consistent paths | Draft |
| S007-FR-015 | Functional | P2 | Configurable namespace separator | Draft |
| S007-FR-016 | Functional | P2 | Configurable source and test extensions | Draft |
| S007-FR-017 | Functional | P1 | NamespaceHelper constructor accepts Settings or raw roots | Draft |
| S007-IF-001 | Interface | P1 | NamespaceHelper::pathToFqcn signature | Draft |
| S007-IF-002 | Interface | P1 | NamespaceHelper::sourcePathToTestPath signature | Draft |
| S007-IF-003 | Interface | P1 | NamespaceHelper::normalizeRelativePath signature | Draft |
| S007-IF-004 | Interface | P1 | NamespaceHelper constructor overloads | Draft |
| S007-IF-005 | Interface | P1 | Structure block config schema | Draft |
| S007-IF-006 | Interface | P1 | file_map config schema | Draft |
| S007-AS-001 | Acceptance | P1 | Standard Laravel source-to-FQCN conversion | Draft |
| S007-AS-002 | Acceptance | P1 | Standard test path derivation | Draft |
| S007-AS-003 | Acceptance | P1 | file_map override applied | Draft |
| S007-AS-004 | Acceptance | P1 | Multiple structure blocks each resolve independently | Draft |
| S007-AS-005 | Acceptance | P1 | Deeply nested path conversion | Draft |
| S007-AS-006 | Acceptance | P1 | Test directory FQCN resolution | Draft |
| S007-AS-007 | Acceptance | P2 | Custom namespace separator | Draft |
| S007-AS-008 | Acceptance | P1 | End-to-end check command uses mapping | Draft |
| S007-EC-001 | Edge Case | P1 | Missing namespace root for first segment | Draft |
| S007-EC-002 | Edge Case | P1 | Leading backslash in input path | Draft |
| S007-EC-003 | Edge Case | P1 | Windows backslash path separators | Draft |
| S007-EC-004 | Edge Case | P1 | Double slashes in path | Draft |
| S007-EC-005 | Edge Case | P1 | Source file at root of source directory (no subdirectory) | Draft |
| S007-EC-006 | Edge Case | P1 | Source path does not start with source base prefix | Draft |
| S007-EC-007 | Edge Case | P2 | Empty file_map value | Draft |
| S007-EC-008 | Edge Case | P1 | Trailing slashes in source/test base paths | Draft |
| S007-EC-009 | Edge Case | P2 | Namespace root with nested directory prefix | Draft |
| S007-EC-010 | Edge Case | P1 | Case mismatch between path and namespace root | Draft |
| S007-EC-011 | Edge Case | P2 | Source extension not present on input path | Draft |
| S007-EC-012 | Edge Case | P2 | Empty or single-segment path | Draft |
| S007-SC-001 | Success | P1 | All PSR-4 path-to-FQCN conversions are correct | Draft |
| S007-SC-002 | Success | P1 | All source-to-test path derivations are correct | Draft |
| S007-SC-003 | Success | P1 | file_map overrides take precedence over derivation | Draft |
| S007-SC-004 | Success | P1 | Path normalization is idempotent | Draft |
| S007-SC-005 | Success | P1 | Multiple structure blocks produce no cross-contamination | Draft |

## Cross-Spec Dependencies

- **Depends on:** S006 (Configuration -- provides `namespace_roots`, `test_suffix`, `test_extension`, `source_extension`, `namespace_separator`, structure blocks, and `file_map` config)
- **Required by:** S002 (Rules System -- `test-exists` rule relies on derived test paths), S004 (Coverage Linkers -- uses FQCNs produced by `pathToFqcn` to validate coverage declarations), S001 (CLI Commands -- `check` command orchestrates the full mapping pipeline)
