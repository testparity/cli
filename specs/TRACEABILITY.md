# Specification Traceability Matrix

This matrix records the current public-release evidence for each active spec. A spec is considered release-ready only when it has implementation coverage, executable tests, public documentation where user-facing, and a verification gate.

| Spec | Implementation | Executable Evidence | Public Documentation | Current Status |
|------|----------------|---------------------|----------------------|----------------|
| S001 CLI commands | `app/Commands/CheckCommand.php`, `app/Commands/InitCommand.php` | `tests/Feature/InitCommandTest.php`, `tests/Feature/CheckCommandConfigTest.php`, sample `check` runs | `README.md`, `docs/CLI.md`, website guide | Implemented with remaining PHAR/release verification |
| S002 Rules system | `app/Rules/`, `app/Rules/RuleRegistry.php` | `tests/Unit/Rules/` | `docs/RULES.md`, website rules guide | Implemented |
| S003 Coverage readers | `app/Services/CoverageReader.php`, `app/Services/PhpUnitXmlCoverageReader.php` | `tests/Unit/Services/CoverageReaderTest.php`, `tests/Unit/Services/PhpUnitXmlCoverageReaderTest.php` | `docs/COVERAGE-FORMATS.md`, website coverage guide | Implemented for Clover XML, Cobertura XML, and PHPUnit XML |
| S004 Coverage linkers | `app/Services/CoverageLinkers/` | `tests/Unit/Services/CoverageLinkers/` | `docs/COVERAGE-FORMATS.md`, website Pest/PHPUnit guides | Implemented for Pest covers and PHPUnit attributes |
| S005 Plugin system | `app/Services/PluginLoader.php` | `tests/Unit/Services/PluginLoaderTest.php` | `docs/PLUGINS.md`, website plugins guide | Implemented with explicit executable-code trust warning |
| S006 Configuration | `app/Settings/Settings.php`, config loading in `CheckCommand` | `tests/Unit/Settings/SettingsTest.php`, `tests/Feature/CheckCommandConfigTest.php` | `docs/CONFIGURATION.md`, website configuration guide | Implemented |
| S007 Path and namespace mapping | `app/Services/NamespaceHelper.php` | `tests/Unit/Services/NamespaceHelperTest.php`, sample matrix | `docs/CONFIGURATION.md`, website configuration guide | Implemented for configurable extensions, suffixes, separators, and roots |
| S008 Output formats | `app/Commands/CheckCommand.php`, `app/Rules/*Rule.php` formatting methods | `tests/Feature/CheckCommandConfigTest.php`, sample JSON runs, rule output tests | `docs/CLI.md`, website CI/CLI guide | Implemented for table and JSON; broader JSON schema assertions still desirable |
| S009 Documentation system | `docs/`, `README.md`, `../parity-website/guide/`, `../parity-website/.vitepress/config.ts` | `npm run build` in `parity-website`; prior desktop/mobile browser verification | Website guide pages and specs page | Implemented for current guide set |
| S010 Testing, CI, binary, samples | `.github/workflows/ci.yml`, `box.json`, `dev/`, `samples/`, `parity.yaml` | `composer validate --strict`, Pint, Pest with coverage, `php parity check --format=json`, sample feature tests, website build | `docs/RELEASE.md`, `samples/README.md` | Mostly implemented; final PHAR smoke and remote CI still need release-time confirmation |

## Verification Gates

- `composer validate --strict`
- `./vendor/bin/pint --test`
- `XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-xml=coverage-xml --coverage-clover=clover.xml --colors=never`
- `php parity check --format=json`
- `npm run build` from `parity-website/`
- Manual visual check of the VitePress site on desktop and mobile after website changes
- PHAR build and smoke test before tagging a release
