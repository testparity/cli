# S005: Plugin System

| Field | Value |
|-------|-------|
| Spec | S005 |
| Feature | Plugin System |
| Date | 2026-04-23 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

The Plugin System enables users and package authors to extend Parity with custom rules without modifying Parity's core codebase. It provides three discovery locations -- project-local, global user, and Composer packages -- each targeting a different distribution scope. The `PluginLoader` service scans all three locations, loads PHP files or instantiates declared classes, and registers any valid `RuleInterface` implementations into the `RuleRegistry`.

Plugin files loaded from the project-local and global-user directories are plain PHP files that return either a single `RuleInterface` instance or an array of instances. Composer-distributed plugins declare their rule class FQCNs in their package's `composer.json` under `extra.parity.rules`, and the loader instantiates them via the project's Composer autoloader. Once registered, plugin rules are indistinguishable from built-in rules -- they participate identically in configuration resolution, evaluation, table output, and enforcement (see S002).

The three discovery locations are loaded in a deterministic order: project-local first, then global-user, then Composer packages. Because `RuleRegistry` uses last-write-wins semantics (S002-FR-004.e), later sources override earlier ones when name collisions occur. This means Composer package rules override global-user rules, and global-user rules override project-local rules. However, all three override built-in rules that were registered before plugin loading. Plugin loading errors are captured as warnings rather than fatal errors, ensuring a single broken plugin does not prevent Parity from running.

## User Scenarios

S005-US-001 [P1] As a developer, I want to write a custom rule in `.parity/plugins/` that only applies to my current project, so that I can enforce project-specific conventions.

S005-US-002 [P2] As a developer working on multiple projects, I want to place shared custom rules in `~/.parity/plugins/` so that they are available across all my projects without duplication.

S005-US-003 [P2] As a package author, I want to distribute custom Parity rules via Composer so that teams can `composer require` my package and immediately use my rules.

S005-US-004 [P1] As a developer, I want plugin loading errors to produce warnings instead of fatal errors so that one broken plugin does not prevent me from running Parity.

S005-US-005 [P2] As a developer, I want to override a global plugin with a project-local one of the same name so that I can customize behavior per-project.

S005-US-006 [P1] As a developer, I want my custom rule to appear in the check output table and participate in pass/fail evaluation exactly like built-in rules.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S005-FR-001 | Functional | P1 | Three-location plugin discovery | Draft |
| S005-FR-002 | Functional | P1 | Discovery order and precedence | Draft |
| S005-FR-003 | Functional | P1 | Project-local plugin loading | Draft |
| S005-FR-004 | Functional | P2 | Global-user plugin loading | Draft |
| S005-FR-005 | Functional | P2 | Composer package plugin discovery | Draft |
| S005-FR-006 | Functional | P1 | Plugin file return contract | Draft |
| S005-FR-007 | Functional | P1 | RuleInterface compliance required | Draft |
| S005-FR-008 | Functional | P1 | Error handling and warnings | Draft |
| S005-FR-009 | Functional | P1 | Name collision resolution | Draft |
| S005-FR-010 | Functional | P1 | Plugin-to-registry integration | Draft |
| S005-FR-011 | Functional | P2 | Composer autoloader bootstrapping | Draft |
| S005-FR-012 | Functional | P2 | Composer v1 and v2 installed.json compatibility | Draft |
| S005-FR-013 | Functional | P1 | Alphabetical file loading within a directory | Draft |
| S005-FR-014 | Functional | P1 | No sandboxing -- same-process execution | Draft |
| S005-IF-001 | Interface | P1 | PluginLoader public API | Draft |
| S005-IF-002 | Interface | P1 | Plugin file return type contract | Draft |
| S005-IF-003 | Interface | P2 | Composer extra.parity.rules schema | Draft |
| S005-IF-004 | Interface | P2 | Plugin authoring minimal example | Draft |
| S005-AS-001 | Acceptance | P1 | Project-local plugin discovered and registered | Draft |
| S005-AS-002 | Acceptance | P2 | Global-user plugin discovered and registered | Draft |
| S005-AS-003 | Acceptance | P2 | Composer plugin discovered and registered | Draft |
| S005-AS-004 | Acceptance | P1 | Plugin returning array registers multiple rules | Draft |
| S005-AS-005 | Acceptance | P1 | Invalid plugin produces warning, not fatal error | Draft |
| S005-AS-006 | Acceptance | P1 | Name collision resolved by last-write-wins | Draft |
| S005-AS-007 | Acceptance | P1 | Plugin rule evaluated like built-in rule | Draft |
| S005-AS-008 | Acceptance | P1 | Plugin rule appears in table output | Draft |
| S005-AS-009 | Acceptance | P2 | Missing plugin directory silently skipped | Draft |
| S005-AS-010 | Acceptance | P2 | Plugin with throwing constructor produces warning | Draft |
| S005-AS-011 | Acceptance | P2 | Composer plugin with missing class produces warning | Draft |
| S005-SC-001 | Success | P1 | Plugin isolation from core | Draft |
| S005-SC-002 | Success | P1 | All three discovery paths functional | Draft |
| S005-SC-003 | Success | P1 | Error resilience verified | Draft |
| S005-SC-004 | Success | P1 | Name collision semantics verified | Draft |
| S005-SC-005 | Success | P2 | Composer distribution end-to-end | Draft |
| S005-EC-001 | Edge Case | P1 | Plugin directory does not exist | Draft |
| S005-EC-002 | Edge Case | P1 | Plugin file returns non-RuleInterface value | Draft |
| S005-EC-003 | Edge Case | P1 | Plugin file throws exception on require | Draft |
| S005-EC-004 | Edge Case | P2 | Plugin file returns empty array | Draft |
| S005-EC-005 | Edge Case | P2 | Plugin file returns array with mixed valid and invalid items | Draft |
| S005-EC-006 | Edge Case | P1 | Composer class does not implement RuleInterface | Draft |
| S005-EC-007 | Edge Case | P1 | Composer class does not exist | Draft |
| S005-EC-008 | Edge Case | P2 | Composer installed.json is malformed JSON | Draft |
| S005-EC-009 | Edge Case | P2 | Composer installed.json is missing entirely | Draft |
| S005-EC-010 | Edge Case | P2 | HOME environment variable is unset | Draft |
| S005-EC-011 | Edge Case | P2 | Plugin name collides with built-in rule | Draft |
| S005-EC-012 | Edge Case | P2 | Plugin directory contains non-PHP files | Draft |
| S005-EC-013 | Edge Case | P2 | Composer extra.parity.rules contains non-string entry | Draft |
| S005-EC-014 | Edge Case | P2 | Plugin file returns null | Draft |
| S005-EC-015 | Edge Case | P2 | Composer package declares empty rules array | Draft |
| S005-NF-001 | Non-Functional | P2 | Plugin loading performance | Draft |
| S005-NF-002 | Non-Functional | P2 | Security considerations | Draft |

## Cross-Spec Dependencies

- **Depends on:** S002 (Rules System provides `RuleInterface`, `RuleResult`, `RuleContext`, and `RuleRegistry` that plugins implement and register into), S006 (Configuration provides `parity.yaml` where custom rules are referenced by name)
- **Required by:** S001 (CLI check command triggers `PluginLoader::loadAll()` before rule evaluation), S002-AS-015 (plugin rules evaluated identically to built-in rules)
