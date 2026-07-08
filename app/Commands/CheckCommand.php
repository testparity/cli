<?php

declare(strict_types=1);

namespace App\Commands;

use App\Rules\CoverageAttributionRule;
use App\Rules\RuleContext;
use App\Rules\RuleInterface;
use App\Rules\RuleRegistry;
use App\Services\CoverageReader;
use App\Services\NamespaceHelper;
use App\Services\ParityChecker;
use App\Services\ParityPerTestCoverageReader;
use App\Services\ParityJsonCoverageReader;
use App\Services\PhpUnitXmlCoverageReader;
use App\Services\PluginLoader;
use App\Settings\Settings;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Specs: S001, S002, S003, S008
 */
class CheckCommand extends Command
{
    protected $signature = 'check
        {--show-tests : Show test names that cover each file in the table (attribution formats only: Parity JSON or PHPUnit XML; default is count only)}
        {--format=table : Output format: table (default) or json}
        {--config= : Path to parity.yaml (default: ./parity.yaml)}';

    protected $description = 'Check structural parity and code coverage per file using pluggable rules; does not run tests';

    public function handle(RuleRegistry $ruleRegistry): int
    {
        $configOption = $this->option('config');
        $projectRoot = $configOption
            ? $this->resolveProjectRootFromConfig((string) $configOption)
            : $this->resolveProjectRoot();

        if ($projectRoot === null) {
            $this->error('parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.');

            return self::FAILURE;
        }

        $configPath = $configOption
            ? (realpath((string) $configOption) ?: (string) $configOption)
            : $projectRoot.'/parity.yaml';
        $config = $this->loadConfig($configPath);
        if ($config === null) {
            return self::FAILURE;
        }

        // ── Settings ───────────────────────────────────────────────
        $settings = Settings::fromConfig($config);
        $namespaceHelper = new NamespaceHelper(settings: $settings);
        $isJson = $this->option('format') === 'json';

        // ── Plugins ──────────────────────────────────────────────
        $pluginLoader = new PluginLoader;
        $pluginLoader->loadAll($ruleRegistry, $projectRoot);
        if (! $isJson) {
            foreach ($pluginLoader->getWarnings() as $warning) {
                $this->warn("Plugin: {$warning}");
            }
        }

        // ── Coverage data ──────────────────────────────────────────
        $coverageData = $this->loadCoverageData($settings, $projectRoot);
        if ($coverageData === null) {
            return self::FAILURE;
        }

        $coverageMap = $coverageData['coverageMap'];
        $testsByFile = $coverageData['testsByFile'];
        $lineCoverage = $coverageData['lineCoverage'];
        $totalExecutable = $coverageData['totalExecutable'];
        $globalPercent = $coverageData['globalPercent'];
        $hasAttributionCoverage = $coverageData['hasAttributionCoverage'];

        // ── Global coverage check ─────────────────────────────────
        $minCoverageGlobal = $settings->minCoverageGlobal;
        if (! $isJson && $minCoverageGlobal !== null && $globalPercent !== null) {
            if ($globalPercent >= $minCoverageGlobal) {
                $this->info("Global coverage: {$globalPercent}% (minimum: {$minCoverageGlobal}%).");
            } else {
                $this->error("Global coverage {$globalPercent}% is below minimum {$minCoverageGlobal}%.");
            }
        }

        // ── Structures ─────────────────────────────────────────────
        $structures = $config['structure'] ?? [];
        if ($structures === []) {
            $this->warn('No structure entries in parity.yaml.');

            return self::SUCCESS;
        }

        $checker = new ParityChecker($namespaceHelper, $projectRoot);
        $showTestNames = (bool) $this->option('show-tests');
        $hasFailure = $globalPercent !== null && $minCoverageGlobal !== null && $globalPercent < $minCoverageGlobal;
        $expectedTestPaths = [];
        $allFileCoverages = [];
        $jsonReport = [
            'passed' => true,
            'global_coverage' => $globalPercent,
            'min_coverage_global' => $minCoverageGlobal,
            'structures' => [],
        ];

        foreach ($structures as $entry) {
            $name = $entry['name'] ?? 'Unnamed';
            $sourcePath = $this->resolvePath($entry, 'source');
            $testPath = $this->resolvePath($entry, 'test');
            $fileMap = isset($entry['file_map']) && is_array($entry['file_map']) ? $entry['file_map'] : [];

            // Resolve rules for this structure
            try {
                $resolvedRules = $this->resolveStructureRules($entry, $settings, $ruleRegistry, $hasAttributionCoverage);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $sourceDir = $projectRoot.'/'.trim($sourcePath, '/');
            if (! is_dir($sourceDir)) {
                $this->warn("Source path does not exist: {$sourcePath}");

                continue;
            }

            $jsonStructureFiles = [];

            if (! $isJson) {
                $this->title("Structure: {$name}");
                $this->line("  <fg=gray>Source: {$sourcePath}</>");
                $this->line("  <fg=gray>Tests:  {$testPath}</>");
            }

            $phpFiles = File::allFiles($sourceDir);
            $phpFiles = array_filter($phpFiles, fn ($f) => str_ends_with($f->getFilename(), $settings->sourceExtension));

            $fileRows = [];
            foreach ($phpFiles as $file) {
                $relativeSource = $namespaceHelper->normalizeRelativePath($file->getRelativePathname());
                $fullSourceRelative = trim($sourcePath, '/').'/'.$relativeSource;
                $mappedTest = $fileMap[$relativeSource] ?? null;
                $expectedTestRelative = $mappedTest !== null
                    ? trim($testPath, '/').'/'.$mappedTest
                    : $namespaceHelper->sourcePathToTestPath($fullSourceRelative, trim($sourcePath, '/'), trim($testPath, '/'));
                $expectedSourceFqcn = $namespaceHelper->pathToFqcn($fullSourceRelative);
                $testAbsolute = $projectRoot.'/'.$expectedTestRelative;
                $sourceAbsolute = $projectRoot.'/'.$fullSourceRelative;

                $testExists = is_file($testAbsolute);
                $testContent = $testExists ? @file_get_contents($testAbsolute) ?: null : null;

                $normalizedSource = realpath($sourceAbsolute) ?: $sourceAbsolute;
                $coveragePercent = $coverageMap[$normalizedSource] ?? $coverageMap[$fullSourceRelative] ?? 0.0;
                $coveringTests = $testsByFile[$normalizedSource] ?? $testsByFile[$fullSourceRelative] ?? [];

                $expectedTestClass = pathinfo($expectedTestRelative, PATHINFO_FILENAME);
                $perLine = $lineCoverage[$normalizedSource] ?? $lineCoverage[$fullSourceRelative] ?? [];
                $executable = $totalExecutable[$normalizedSource] ?? $totalExecutable[$fullSourceRelative] ?? 0;

                // Build rule context with all available data
                $context = new RuleContext(
                    sourceAbsolutePath: $normalizedSource,
                    sourceRelativePath: $fullSourceRelative,
                    expectedSourceFqcn: $expectedSourceFqcn,
                    testAbsolutePath: $testExists ? $testAbsolute : null,
                    testRelativePath: $testExists ? $expectedTestRelative : null,
                    testExists: $testExists,
                    testContent: $testContent,
                    coveragePercent: $coveragePercent,
                    matchedCoveragePercent: null,
                    coveringTests: $coveringTests,
                    projectRoot: $projectRoot,
                    expectedTestClassName: $expectedTestClass,
                    lineCoverage: $perLine,
                    totalExecutableLines: $executable,
                );

                // Evaluate all rules
                $ruleResults = [];
                $allPassed = true;
                foreach ($resolvedRules as $resolved) {
                    /** @var RuleInterface $rule */
                    $rule = $resolved['rule'];
                    $result = $rule->evaluate($context, $resolved['params']);
                    $ruleResults[] = ['rule' => $rule, 'result' => $result, 'params' => $resolved['params']];
                    if ($rule->isEnforced() && ! $result->passed) {
                        $allPassed = false;
                    }
                }

                if (! $allPassed) {
                    $hasFailure = true;
                }

                $expectedTestPaths[$expectedTestRelative] = true;
                $allFileCoverages[] = $coveragePercent;

                $dirUnderSource = dirname($relativeSource);
                $relativeTest = ($dirUnderSource === '.' ? '' : $dirUnderSource.'/').basename($expectedTestRelative);

                $fileRows[] = [
                    'relativeSource' => $relativeSource,
                    'relativeTest' => $relativeTest,
                    'ruleResults' => $ruleResults,
                    'allPassed' => $allPassed,
                ];

                // Collect JSON data
                $jsonFileEntry = [
                    'source' => $fullSourceRelative,
                    'test' => $expectedTestRelative,
                    'passed' => $allPassed,
                    'rules' => [],
                ];
                foreach ($ruleResults as $rr) {
                    $jsonFileEntry['rules'][$rr['rule']->name()] = [
                        'passed' => $rr['result']->passed,
                        'value' => $rr['result']->value,
                        'error' => $rr['result']->error,
                    ];
                }
                $jsonStructureFiles[] = $jsonFileEntry;
            }

            $jsonReport['structures'][] = [
                'name' => $name,
                'source_path' => $sourcePath,
                'test_path' => $testPath,
                'files' => $jsonStructureFiles,
            ];

            // Build dynamic table (skip in JSON mode)
            if (! $isJson) {
                $tableRows = $this->buildDynamicTableRows($fileRows, $resolvedRules);
                if ($tableRows !== []) {
                    $this->newLine();
                    $headers = $this->buildDynamicHeaders($resolvedRules);
                    $this->table($headers, $tableRows);
                    $this->newLine();
                }
            }
        }

        $jsonReport['passed'] = ! $hasFailure;

        // ── Output ────────────────────────────────────────────────
        if ($isJson) {
            $this->line(json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $hasFailure ? self::FAILURE : self::SUCCESS;
        }

        // ── Summary (table mode) ──────────────────────────────────
        $minCoverageDefault = $settings->minCoverage;
        $minMatchedCoverageDefault = $settings->minMatchedCoverage;
        $perFileMin = $allFileCoverages !== [] ? min($allFileCoverages) : null;
        $perFileAvg = $allFileCoverages !== [] ? round(array_sum($allFileCoverages) / count($allFileCoverages), 2) : null;
        $this->title('Summary');
        $this->outputCoverageSummaryTable($globalPercent, $minCoverageGlobal, $minCoverageDefault, $minMatchedCoverageDefault, $perFileMin, $perFileAvg);

        if ($hasAttributionCoverage && $testsByFile !== []) {
            $this->warnTestsNotMatchingAnyStructure($testsByFile, $expectedTestPaths, $namespaceHelper);
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    // ── Config resolution ──────────────────────────────────────────

    /**
     * Resolve the path from either new `paths:` format or legacy `source_path`/`test_path`.
     */
    private function resolvePath(array $entry, string $key): string
    {
        // New format: paths: { source: ..., test: ... }
        if (isset($entry['paths']) && is_array($entry['paths'])) {
            return (string) ($entry['paths'][$key] ?? '');
        }

        // Legacy format: source_path / test_path
        return (string) ($entry["{$key}_path"] ?? '');
    }

    /**
     * Resolve rules for a structure entry. Supports:
     *   - New format: rules: [{ minimum-coverage: { min: 80 } }, 'enforce-coverage-link']
     *   - Legacy format: enforce_attribute / enforce_coverage_link + min_coverage
     */
    private function resolveStructureRules(array $entry, Settings $settings, RuleRegistry $registry, bool $hasAttributionCoverage = false): array
    {
        // New format: explicit rules array
        if (isset($entry['rules']) && is_array($entry['rules'])) {
            $ruleConfigs = $entry['rules'];

            // Auto-prepend test-exists if not present
            $ruleNames = array_map(function ($rc) {
                return is_string($rc) ? $rc : (isset($rc['name']) ? $rc['name'] : array_key_first($rc));
            }, $ruleConfigs);

            if (! in_array('test-exists', $ruleNames, true)) {
                array_unshift($ruleConfigs, 'test-exists');
            }

            // Auto-append attribution rules if the selected coverage format exposes per-test data.
            if ($hasAttributionCoverage) {
                if (! in_array('matched-coverage', $ruleNames, true)) {
                    $ruleConfigs[] = 'matched-coverage';
                }
                if (! in_array('coverage-attribution', $ruleNames, true)) {
                    $ruleConfigs[] = 'coverage-attribution';
                }
            }

            return $registry->resolve($ruleConfigs);
        }

        // Legacy format: build rules from old config keys
        $ruleConfigs = ['test-exists'];

        // Coverage link enforcement
        $enforceCoverageLink = $entry['enforce_coverage_link'] ?? null;
        $enforceAttribute = $entry['enforce_attribute'] ?? null;

        if ($enforceCoverageLink === true || $enforceCoverageLink === 'true') {
            $linkParams = [];
            if (isset($entry['linkers']) && is_array($entry['linkers'])) {
                $linkParams['linkers'] = $entry['linkers'];
            }
            if (is_string($enforceAttribute)) {
                $linkParams['attribute'] = $enforceAttribute;
            }
            $ruleConfigs[] = $linkParams !== [] ? ['enforce-coverage-link' => $linkParams] : 'enforce-coverage-link';
        } elseif (is_string($enforceAttribute)) {
            $ruleConfigs[] = ['enforce-coverage-link' => ['attribute' => $enforceAttribute]];
        } else {
            $ruleConfigs[] = 'enforce-coverage-link';
        }

        // Coverage minimum
        $minCoverage = isset($entry['min_coverage'])
            ? (float) $entry['min_coverage']
            : $settings->minCoverage;
        $ruleConfigs[] = ['minimum-coverage' => ['min' => $minCoverage]];

        // Attribution-capable coverage extras (legacy auto-add)
        if ($hasAttributionCoverage) {
            $matchedMin = isset($entry['min_matched_coverage'])
                ? (float) $entry['min_matched_coverage']
                : ($settings->minMatchedCoverage !== null ? $settings->minMatchedCoverage : null);
            $matchedParams = $matchedMin !== null ? ['min' => $matchedMin] : [];
            $ruleConfigs[] = $matchedParams !== [] ? ['matched-coverage' => $matchedParams] : 'matched-coverage';
            $ruleConfigs[] = 'coverage-attribution';
        }

        return $registry->resolve($ruleConfigs);
    }

    // ── Dynamic table rendering ────────────────────────────────────

    /**
     * Build headers dynamically from resolved rules.
     */
    private function buildDynamicHeaders(array $resolvedRules): array
    {
        $headers = ['Source', 'Test'];

        foreach ($resolvedRules as $resolved) {
            $header = $resolved['rule']->columnHeader();
            if ($header !== null) {
                $headers[] = $header;
                // CoverageAttributionRule adds a second "Other" column
                if ($resolved['rule'] instanceof CoverageAttributionRule) {
                    $headers[] = 'Other';
                }
            }
        }

        $headers[] = 'OK';

        return $headers;
    }

    /**
     * Build table rows dynamically from rule results.
     */
    private function buildDynamicTableRows(array $fileRows, array $resolvedRules): array
    {
        if ($fileRows === []) {
            return [];
        }

        usort($fileRows, fn (array $a, array $b) => strcmp($a['relativeSource'], $b['relativeSource']));

        // Count total columns from rules (including extra CoverageAttribution column)
        $ruleColumnCount = 0;
        foreach ($resolvedRules as $resolved) {
            if ($resolved['rule']->columnHeader() !== null) {
                $ruleColumnCount++;
                if ($resolved['rule'] instanceof CoverageAttributionRule) {
                    $ruleColumnCount++;
                }
            }
        }

        $rows = [];
        $lastDir = null;
        $gray = '<fg=gray>%s</>';

        foreach ($fileRows as $row) {
            $dir = dirname($row['relativeSource']);

            // Directory separator row
            if ($dir !== $lastDir && $dir !== '.') {
                $lastDir = $dir;
                $dirRow = [
                    sprintf($gray, $dir.'/'),
                    sprintf($gray, $dir.'/'),
                ];
                for ($i = 0; $i < $ruleColumnCount; $i++) {
                    $dirRow[] = sprintf($gray, '-');
                }
                $dirRow[] = sprintf($gray, '-'); // OK column
                $rows[] = $dirRow;
            }
            if ($dir === '.') {
                $lastDir = $dir;
            }

            $sourceCell = ($dir === '.' ? '' : '  ').basename($row['relativeSource']);
            $testCell = ($dir === '.' ? '' : '  ').basename($row['relativeTest']);

            $tableRow = [$sourceCell, $testCell];

            // Dynamic rule columns
            foreach ($row['ruleResults'] as $rr) {
                /** @var RuleInterface $rule */
                $rule = $rr['rule'];
                if ($rule->columnHeader() !== null) {
                    $tableRow[] = $rule->formatCell($rr['result']);
                    // CoverageAttributionRule adds a second "Other" column
                    if ($rule instanceof CoverageAttributionRule) {
                        $tableRow[] = $rule->formatOtherCell($rr['result']);
                    }
                }
            }

            // OK column
            $tableRow[] = $row['allPassed'] ? '<fg=green>Y</>' : '<fg=red>N</>';

            $rows[] = $tableRow;
        }

        return $rows;
    }

    // ── Coverage data loading ──────────────────────────────────────

    private function loadCoverageData(Settings $settings, string $projectRoot): ?array
    {
        $coverageCandidates = $settings->coveragePaths;
        $minCoverageGlobal = $settings->minCoverageGlobal;

        $coveragePath = null;
        $hasAttributionCoverage = false;
        foreach ($coverageCandidates as $candidate) {
            $path = $projectRoot.'/'.ltrim((string) $candidate, '/');
            if (is_dir($path) && is_file($path.'/index.json')) {
                $coveragePath = $path;
                $hasAttributionCoverage = true;
                break;
            }
            if (is_file($path)) {
                $coveragePath = $path;
                $hasAttributionCoverage = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json';
                break;
            }
            if (is_dir($path) && is_file($path.'/index.xml')) {
                $coveragePath = $path;
                $hasAttributionCoverage = true;
                break;
            }
        }

        if ($coveragePath === null) {
            $tried = implode(', ', array_map(fn ($c) => (string) $c, $coverageCandidates));
            $this->error("No coverage file or directory found (tried: {$tried}).");

            return null;
        }

        $coverageMap = [];
        $testsByFile = [];
        $lineCoverage = [];
        $totalExecutable = [];
        $globalPercent = null;

        if ($coveragePath !== null && is_dir($coveragePath) && is_file($coveragePath.'/index.json')) {
            $reader = new ParityPerTestCoverageReader;
            $result = $reader->read($coveragePath, $projectRoot);
            $coverageMap = $result['coverage'];
            $testsByFile = $result['testsByFile'];
            $lineCoverage = $result['lineCoverage'] ?? [];
            $totalExecutable = $result['totalExecutable'] ?? [];
            $globalPercent = $result['globalPercent'];
        } elseif ($coveragePath !== null && is_file($coveragePath) && strtolower(pathinfo($coveragePath, PATHINFO_EXTENSION)) === 'json') {
            $jsonReader = new ParityJsonCoverageReader;
            $result = $jsonReader->read($coveragePath, $projectRoot);
            $coverageMap = $result['coverage'];
            $testsByFile = $result['testsByFile'];
            $lineCoverage = $result['lineCoverage'] ?? [];
            $totalExecutable = $result['totalExecutable'] ?? [];
            $globalPercent = $result['globalPercent'];
        } elseif ($hasAttributionCoverage) {
            $phpUnitReader = new PhpUnitXmlCoverageReader;
            $result = $phpUnitReader->read($coveragePath, $projectRoot);
            $coverageMap = $result['coverage'];
            $testsByFile = $result['testsByFile'];
            $lineCoverage = $result['lineCoverage'] ?? [];
            $totalExecutable = $result['totalExecutable'] ?? [];
            $globalPercent = $result['globalPercent'];
        } else {
            $coverageReader = new CoverageReader;
            $coverageMap = $coverageReader->read($coveragePath, $projectRoot);
            $globalPercent = $minCoverageGlobal !== null ? $coverageReader->readGlobalCoverage($coveragePath) : null;
        }

        return compact('coverageMap', 'testsByFile', 'lineCoverage', 'totalExecutable', 'globalPercent', 'hasAttributionCoverage');
    }

    private function warnTestsNotMatchingAnyStructure(array $testsByFile, array $expectedTestPaths, NamespaceHelper $namespaceHelper): void
    {
        $allTestNames = [];
        foreach ($testsByFile as $list) {
            foreach ($list as $name) {
                $allTestNames[$name] = true;
            }
        }
        $testPathsInCoverage = [];
        foreach (array_keys($allTestNames) as $testName) {
            $class = str_contains($testName, '::') ? explode('::', $testName, 2)[0] : $testName;
            $path = $this->testClassToRelativePath($class);
            if ($path !== '') {
                $testPathsInCoverage[$path] = true;
            }
        }
        $expectedNormalized = [];
        foreach (array_keys($expectedTestPaths) as $expected) {
            $expectedNormalized[$namespaceHelper->normalizeRelativePath($expected)] = true;
        }
        $unmatched = [];
        foreach (array_keys($testPathsInCoverage) as $path) {
            $normalized = $namespaceHelper->normalizeRelativePath($path);
            if (! isset($expectedNormalized[$normalized])) {
                $unmatched[] = $path;
            }
        }
        if ($unmatched === []) {
            return;
        }
        sort($unmatched);
        $this->newLine();
        $this->warn('Tests that did not match any structure (e.g. wrong path/namespace):');
        foreach ($unmatched as $path) {
            $this->line('  <fg=yellow>'.$path.'</>');
        }
    }

    private function testClassToRelativePath(string $testClass): string
    {
        $class = $testClass;
        if (str_starts_with($class, 'P\\')) {
            $class = substr($class, 2);
        }
        $class = str_replace('\\', '/', $class);
        $segments = explode('/', $class);
        if ($segments === []) {
            return '';
        }
        if (strtolower($segments[0]) === 'tests') {
            $segments[0] = 'tests';
        }

        return implode('/', $segments).'.php';
    }

    private function outputCoverageSummaryTable(?float $globalPercent, ?float $minCoverageGlobal, float $minCoverageDefault, ?float $minMatchedCoverage = null, ?float $perFileMin = null, ?float $perFileAvg = null): void
    {
        $rows = [];

        $globalValue = $globalPercent !== null ? sprintf('%.2f%%', $globalPercent) : '—';
        $globalRequired = $minCoverageGlobal !== null ? sprintf('%.0f%%', $minCoverageGlobal) : '—';
        $globalOk = $globalPercent !== null && $minCoverageGlobal !== null && $globalPercent >= $minCoverageGlobal;
        $globalStatus = $minCoverageGlobal !== null
            ? ($globalOk ? '<fg=green>OK</>' : '<fg=red>FAIL</>')
            : '<fg=gray>—</>';
        $rows[] = ['Global', $globalValue, $globalRequired, $globalStatus];

        $perFileAvgValue = $perFileAvg !== null ? sprintf('%.2f%%', $perFileAvg) : '—';
        $rows[] = ['Per-file avg (all tests)', $perFileAvgValue, '—', '<fg=gray>—</>'];

        $perFileValue = $perFileMin !== null ? sprintf('%.2f%%', $perFileMin) : '—';
        $perFileOk = $perFileMin !== null && $perFileMin >= $minCoverageDefault;
        $perFileStatus = $perFileMin !== null
            ? ($perFileOk ? '<fg=green>OK</>' : '<fg=red>FAIL</>')
            : '<fg=gray>—</>';
        $rows[] = ['Per-file min (all tests)', $perFileValue, sprintf('%.0f%%', $minCoverageDefault), $perFileStatus];

        if ($minMatchedCoverage !== null) {
            $rows[] = ['Per-file min (matching test only)', '—', sprintf('%.0f%%', $minMatchedCoverage), '<fg=gray>—</>'];
        }

        $this->newLine();
        $this->table(['Coverage', 'Value', 'Required', 'Status'], $rows);
        $this->newLine();
    }

    protected function resolveProjectRoot(): ?string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return null;
        }
        if (! is_file($cwd.'/parity.yaml')) {
            return null;
        }

        return realpath($cwd) ?: $cwd;
    }

    protected function resolveProjectRootFromConfig(string $configPath): ?string
    {
        $resolved = realpath($configPath);
        if ($resolved === false || ! is_file($resolved)) {
            return null;
        }

        return dirname($resolved);
    }

    protected function loadConfig(string $configPath): ?array
    {
        if (! is_file($configPath)) {
            return null;
        }
        $contents = @file_get_contents($configPath);
        if ($contents === false) {
            $this->error("Could not read config: {$configPath}");

            return null;
        }
        if (class_exists(Yaml::class)) {
            try {
                $config = Yaml::parse($contents);
            } catch (ParseException $e) {
                $this->error("Invalid YAML in {$configPath}: {$e->getMessage()}");

                return null;
            }

            return is_array($config) ? $config : null;
        }
        $this->error('Install symfony/yaml to use parity.yaml, or use a PHP config.');

        return null;
    }
}
