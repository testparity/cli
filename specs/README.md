# Parity Specifications

Parity uses spec IDs to keep implementation, tests, documentation, and release gates aligned. This directory is the canonical public specification set for the CLI repository.

## Active Specs

| Spec | Area | Primary Code | Primary Tests | Public Docs |
|------|------|--------------|---------------|-------------|
| S001 | CLI commands and interface | `app/Commands/CheckCommand.php`, `app/Commands/InitCommand.php` | `tests/Feature/` | `README.md`, `docs/CLI.md` |
| S002 | Rules system | `app/Rules/` | `tests/Unit/Rules/` | `docs/RULES.md` |
| S003 | Coverage readers | `app/Services/CoverageReader.php`, `app/Services/PhpUnitXmlCoverageReader.php` | `tests/Unit/Services/` | `docs/COVERAGE-FORMATS.md` |
| S004 | Coverage linkers | `app/Services/CoverageLinkers/` | `tests/Unit/Services/CoverageLinkers/` | `docs/COVERAGE-FORMATS.md` |
| S005 | Plugin system | `app/Services/PluginLoader.php` | `tests/Unit/Services/PluginLoaderTest.php` | `docs/PLUGINS.md` |
| S006 | Configuration | `app/Settings/Settings.php` | `tests/Unit/Settings/` | `docs/CONFIGURATION.md` |
| S007 | Path and namespace mapping | `app/Services/NamespaceHelper.php` | `tests/Unit/Services/NamespaceHelperTest.php` | `docs/CONFIGURATION.md` |
| S008 | Output formats | `app/Commands/CheckCommand.php`, rule formatter methods | `tests/Feature/CheckCommandConfigTest.php`, sample JSON runs | `docs/CLI.md` |
| S009 | Documentation system | `README.md`, `docs/`, `../parity-website/` | Website build and visual checks | `docs/` |
| S010 | Testing, CI, and binary distribution | `.github/workflows/ci.yml`, `box.json`, `dev/` | Full test suite, Pint, PHAR smoke tests | `docs/RELEASE.md` |
| S011 | Parity test execution and per-test reports | `app/Commands/TestCommand.php`, `app/Services/ParityPerTestCoverageReader.php`, `app/Services/ParityTestArtifactNormalizer.php` | `tests/Feature/TestCommandTest.php`, `tests/Unit/Services/ParityPerTestCoverageReaderTest.php` | `docs/CONFIGURATION.md`, `docs/COVERAGE-FORMATS.md` |

See `TRACEABILITY.md` for the release-readiness matrix that maps specs to current implementation, tests, docs, and verification gates.

## Traceability Rule

Every new production behavior must reference at least one spec ID in one of these places:

- A test file comment near the covered behavior, for example `// Specs: S001-FR-006, S010-FR-005`.
- A public documentation page that names the spec-backed feature.
- A changelog entry when the behavior changes an existing public contract.

Tests are the preferred traceability location because they prove the spec contract is executable.
