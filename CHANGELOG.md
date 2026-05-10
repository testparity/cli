# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
