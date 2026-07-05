# S009: Documentation System

| Field | Value |
|-------|-------|
| Spec | S009 |
| Feature | Documentation System |
| Date | 2026-04-24 |
| Status | Draft |
| Author | spec-writer-agent |

## Overview

The Parity documentation system spans the core CLI tool (`parity/`) and the VitePress website (`parity-website/`). The website owns the rendered guide source in `parity-website/guide/`; the CLI repo owns package-local docs in `parity/docs/` and public behavior specs in `parity/specs/`. This specification defines the canonical structure of all guides, writing conventions, VitePress configuration, the authoring workflow, requirements for code examples, the versioning strategy, and the synchronization requirements between documentation and implementation.

## User Scenarios

S009-US-001 [P1] As a developer, I want to find a guide that explains what Parity does and how to get started so that I can evaluate the tool without reading source code.

S009-US-002 [P1] As a developer who just installed Parity, I want a step-by-step quick start so that I can verify my installation works before reading full documentation.

S009-US-003 [P1] As an advanced user, I want reference documentation for all configuration options, rules, and plugin APIs so that I can integrate Parity into complex project setups.

S009-US-004 [P1] As a CI engineer, I want documented examples for every major CI platform so that I can copy-paste a working Parity integration.

S009-US-005 [P2] As a documentation maintainer, I want clear authoring workflow and style conventions so that I can keep docs consistent across multiple contributors.

S009-US-006 [P2] As a project maintainer, I want docs to be automatically validated for correctness so that documentation drift from implementation does not silently accumulate.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S009-FR-001 | Functional | P1 | Guide inventory and coverage | Draft |
| S009-FR-002 | Functional | P1 | VitePress configuration | Draft |
| S009-FR-003 | Functional | P1 | Navigation structure | Draft |
| S009-FR-004 | Functional | P1 | Writing style and tone | Draft |
| S009-FR-005 | Functional | P1 | Code example requirements | Draft |
| S009-FR-006 | Functional | P1 | Frontmatter schema | Draft |
| S009-FR-007 | Functional | P1 | Authoring workflow | Draft |
| S009-FR-008 | Functional | P1 | Versioning strategy | Draft |
| S009-FR-009 | Functional | P1 | Docs-implementation sync | Draft |
| S009-FR-010 | Functional | P2 | Single-version constraint | Draft |
| S009-FR-011 | Functional | P2 | Search integration | Draft |
| S009-FR-012 | Functional | P2 | Dark mode support | Draft |
| S009-AS-001 | Acceptance | P1 | Guide renders correctly in VitePress | Draft |
| S009-AS-002 | Acceptance | P1 | Code examples execute successfully | Draft |
| S009-AS-003 | Acceptance | P1 | Navigation links are valid | Draft |
| S009-AS-004 | Acceptance | P1 | Config reference stays in sync | Draft |
| S009-EC-001 | Edge Case | P1 | Stale docs on new feature | Draft |
| S009-EC-002 | Edge Case | P1 | Broken internal links | Draft |
| S009-EC-003 | Edge Case | P1 | Code example outdated after refactor | Draft |
| S009-EC-004 | Edge Case | P2 | Guide for experimental feature not yet merged | Draft |
| S009-SC-001 | Success | P1 | All navigation links resolve | Draft |
| S009-SC-002 | Success | P1 | Code blocks have syntax highlighting | Draft |
| S009-SC-003 | Success | P1 | Mobile navigation is usable | Draft |

## Cross-Spec Dependencies

- **Depends on:** S001 (CLI Commands -- docs must reference correct command signatures and flags), S002 (Rules System -- rule documentation must match rule names and parameters), S005 (Plugin System -- plugin authoring guide must match PluginInterface contract), S006 (Configuration -- config reference must match the full `parity.yaml` schema)
- **Required by:** None (terminal spec)

## Existing Guide Inventory

The following 10 guides exist at `parity-website/guide/`:

| Guide | Title | Category | Covers |
|-------|-------|----------|--------|
| `getting-started.md` | Getting Started | Introduction | What Parity does, quick start, example output |
| `installation.md` | Installation | Introduction | Requirements, install methods, verification, updating |
| `configuration.md` | Configuration | Core Concepts | Full `parity.yaml` reference, settings, structure blocks |
| `rules.md` | Built-in Rules | Core Concepts | All five rules: test-exists, enforce-coverage-link, minimum-coverage, matched-coverage, coverage-attribution |
| `coverage.md` | Coverage Formats | Core Concepts | Clover XML vs PHPUnit XML, generating coverage, rule compatibility |
| `pest-support.md` | Pest PHP Support | Integrations | Pest covers syntax (chained, file-level, multiple), detection, config |
| `phpunit-support.md` | PHPUnit Support | Integrations | CoversClass attribute, string literals, multiple attributes, migration from docblocks |
| `ci-integration.md` | CI/CD Integration | Integrations | Exit codes, JSON output, GitHub Actions, GitLab CI, Bitbucket Pipelines |
| `plugins.md` | Plugin System | Advanced | Custom rule authoring via RuleInterface, plugin locations, parameters, Composer distribution |

## Decisions

- **S009-DR-001**: Single-version documentation (no multi-version). Rationale: Parity ships frequently enough that version skew would be confusing; users are expected to run the latest version. docs/testparity.com always reflects `main`.
- **S009-DR-002**: Markdown source lives in `parity-website/guide/`, built to `parity-website/.vitepress/dist/` by VitePress. The `guide/` directory is source; `.vitepress/dist/` is derived build output.
- **S009-DR-003**: Code examples must be executable against the current `main` branch. Examples that cannot be verified automatically must include a comment noting the verification method.
