# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.2] - 2026-07-30

### Fixed
- Checkout-free release publication now passes the repository explicitly to GitHub CLI.

## [1.2.1] - 2026-07-30

### Fixed
- Release automation tests now use deterministic changelog fixtures and remain valid after a release empties the live `Unreleased` section.

## [1.2.0] - 2026-07-30

### Added
- `parity test` execution mode for running each belonging test independently and generating attribution-capable coverage.
- A versioned Parity per-test report directory with strict manifest, report, and path validation.
- Multiple ordered coverage inputs, allowing attribution-rich reports to take precedence over aggregate fallbacks.
- Automated tagged releases with verified PHAR assets, SHA-256 checksums, and a public Packagist installation smoke test.

### Changed
- Per-test report publication is staged and atomic so failed or timed-out runs preserve the last complete report set.
- Release automation now requires curated changelog notes and identical version metadata across source, PHAR, and Packagist installs.

### Security
- Coverage artifacts and report paths are constrained to owned workspace locations before cleanup or publication.
- Test-command placeholders are shell-escaped, and the executable configuration trust boundary is documented explicitly.

## [1.1.1] - 2026-07-06

### Added
- Public badges and documentation for converting language-neutral coverage JSON.

### Changed
- Package version metadata was synchronized to `v1.1.1`.

## [1.1.0] - 2026-07-06

### Added
- Language-neutral coverage attribution through Parity JSON.
- Portable sample configurations spanning PHP, JavaScript, TypeScript, Rust, and common test runners.
- PHAR version reporting through the tracked `VERSION` file.

### Fixed
- PHAR dependency packaging and runtime version fallback.
- CI dependency setup, portable sample coverage fixtures, and coverage-reader formatting.

## [1.0.0] - 2026-04-26

### Added
- S001: CLI Commands & Interface (`check`, `init`, `list-rules`, `plugin` commands)
- S002: Rules System (RuleInterface, RuleResult, CoverageAttributionRule, StructureRule, TestExistsRule, UnreachableRule, FqcnRule)
- S003: Coverage Readers (PHPUnit XML, PEST register.yaml, Clover XML, Cobertura XML)
- S004: Coverage Linkers (SourceFileLinker, XdebugLinker, PhpstormLinker)
- S005: Plugin System (PluginInterface, PluginLoader, official plugins: git, controller, model, service)
- S006: Configuration & Settings (settings.yml, namespace_roots, coverage_attribution, structure_blocks, file_map, min_coverage_global)
- S007: Path & Namespace Mapping (NamespaceHelper, pathToFqcn, sourcePathToTestPath, normalizeRelativePath)
- S008: Output Formats (table/JSON dual format, dynamic columns, directory grouping, exit codes, plugin warning suppression)
- S009: Documentation System (getting-started, installation, configuration, rules, coverage, pest-support, phpunit-support, ci-integration, plugins guides)
- S010: Testing, CI/CD & Binary Distribution (Pest, Pint, Box PHAR, GitHub Actions workflow)
