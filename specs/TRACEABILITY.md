# Specification Traceability Matrix

This matrix records the current public-release evidence for each active spec. A spec is considered release-ready only when it has implementation coverage, executable tests, public documentation where user-facing, and a verification gate.

| Spec | Implementation | Executable Evidence | Public Documentation | Current Status |
|------|----------------|---------------------|----------------------|----------------|
| S001 CLI commands | `app/Commands/CheckCommand.php`, `app/Commands/InitCommand.php`, `app/Commands/TestCommand.php` | command feature tests, sample `check` runs, packaged command smoke tests | `README.md`, `docs/CLI.md`, website guide | Implemented and verified in the local PHAR |
| S002 Rules system | `app/Rules/`, `app/Rules/RuleRegistry.php` | `tests/Unit/Rules/` | `docs/RULES.md`, website rules guide | Implemented |
| S003 Coverage readers | `app/Services/CoverageReader.php`, `app/Services/PhpUnitXmlCoverageReader.php`, `app/Services/ParityJsonCoverageReader.php`, `app/Services/ParityPerTestCoverageReader.php` | reader unit tests and sample report runs | `docs/COVERAGE-FORMATS.md`, website coverage guide | Implemented for Clover XML, Cobertura XML, PHPUnit XML, Parity JSON, and per-test report directories |
| S004 Coverage linkers | `app/Services/CoverageLinkers/` | `tests/Unit/Services/CoverageLinkers/` | `docs/COVERAGE-FORMATS.md`, website Pest/PHPUnit guides | Implemented for Pest covers and PHPUnit attributes |
| S005 Plugin system | `app/Services/PluginLoader.php` | `tests/Unit/Services/PluginLoaderTest.php` | `docs/PLUGINS.md`, website plugins guide | Implemented with explicit executable-code trust warning |
| S006 Configuration | `app/Settings/Settings.php`, config loading in `CheckCommand` | `tests/Unit/Settings/SettingsTest.php`, `tests/Feature/CheckCommandConfigTest.php` | `docs/CONFIGURATION.md`, website configuration guide | Implemented |
| S007 Path and namespace mapping | `app/Services/NamespaceHelper.php` | `tests/Unit/Services/NamespaceHelperTest.php`, sample matrix | `docs/CONFIGURATION.md`, website configuration guide | Implemented for configurable extensions, suffixes, separators, and roots |
| S008 Output formats | `app/Commands/CheckCommand.php`, `app/Rules/*Rule.php` formatting methods | `tests/Feature/CheckCommandConfigTest.php`, sample JSON runs, rule output tests | `docs/CLI.md`, website CI/CLI guide | Implemented for table and JSON; broader JSON schema assertions still desirable |
| S009 Documentation system | `docs/`, `README.md`, `../parity-website/guide/`, `../parity-website/.vitepress/config.ts` | `npm run build` in `parity-website`; prior desktop/mobile browser verification | Website guide pages and specs page | Implemented for current guide set |
| S010 Testing, CI, binary, samples | `.github/workflows/ci.yml`, `.github/workflows/release.yml`, `.github/workflows/release-smoke.yml`, `box.json`, `dev/`, `samples/`, `parity.yaml` | strict Composer validation, dependency audit, Pint, covered Pest suite, self-check artifact, metadata-mismatch tests, atomic remote-push test, public-consumer `check` + `test` smoke, sample checks, website build, PHAR version/check/checksum verification | `docs/RELEASE.md`, `CHANGELOG.md`, `SECURITY.md`, `samples/README.md` | Implemented and locally verified; exact-tag Packagist and GitHub Release publication remain intentionally gated by the tagged workflow |
| S011 Parity test and per-test reports | `app/Commands/TestCommand.php`, `app/Services/ParityPerTestCoverageReader.php`, `app/Services/ParityTestArtifactNormalizer.php`, `app/Services/ParityTestWorkspace.php` | `tests/Feature/TestCommandTest.php` and focused reader, normalizer, and workspace unit tests | `docs/CONFIGURATION.md`, `docs/COVERAGE-FORMATS.md`, website coverage/configuration guides | Implemented with isolated execution, timeouts, staged publication, path containment, strict report validation, and delegated check flow |

## Verification Gates

- `composer validate --strict`
- `./vendor/bin/pint --test`
- `XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never`
- `php parity check --format=json`
- `composer audit --locked`
- `npm run build` from `parity-website/`
- `npm audit --audit-level=high` from `parity-website/`
- Manual visual check of the VitePress site on desktop and mobile after website changes
- `./vendor/bin/box compile --no-interaction`
- `php parity.phar --version`, `php parity.phar check --format=json`, and checksum verification
