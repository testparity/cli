<?php

declare(strict_types=1);

namespace App\Commands;

use App\Rules\RuleContext;
use App\Rules\RuleInterface;
use App\Rules\RuleRegistry;
use App\Rules\RuleResult;
use App\Services\CoverageReader;
use App\Services\NamespaceHelper;
use App\Services\ParityChecker;
use App\Services\PhpUnitXmlCoverageReader;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Yaml;

class CheckCommand extends Command
{
    protected $signature = 'check
        {--show-tests : Show test names that cover each file in the table (PHPUnit XML only; default is count only)}';

    protected $description = 'Check structural parity and code coverage per file using pluggable rules; does not run tests';

    public function handle(NamespaceHelper $namespaceHelper, RuleRegistry $ruleRegistry): int
    {
        $projectRoot = $this->resolveProjectRoot();
        if ($projectRoot === null) {
            $this->error('parity.yaml not found. Run from project root or place parity.yaml there.');

            return self::FAILURE;
        }

        $configPath = $projectRoot . '/parity.yaml';
        $config = $this->loadConfig($configPath);
        if ($config === null) {
            return self::FAILURE;
        }

        // ── Coverage data ──────────────────────────────────────────
        $coverageData = $this->loadCoverageData($config, $projectRoot);
        if ($coverageData === null) {
            return self::FAILURE;
        }

        $coverageMap = $coverageData['coverageMap'];
        $testsByFile = $coverageData['testsByFile'];
        $lineCoverage = $coverageData['lineCoverage'];
        $totalExecutable = $coverageData['totalExecutable'];
        $globalPercent = $coverageData['globalPercent'];
        $isPhpUnitXml = $coverageData['isPhpUnitXml'];

        // ── Global coverage check ─────────────────────────────────
        $minCoverageGlobal = isset($config['min_coverage_global']) ? (float) $config['min_coverage_global'] : null;
        if ($minCoverageGlobal !== null && $globalPercent !== null) {
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

        foreach ($structures as $entry) {
            $name = $entry['name'] ?? 'Unnamed';
            $sourcePath = $this->resolvePath($entry, 'source');
            $testPath = $this->resolvePath($entry, 'test');
            $fileMap = isset($entry['file_map']) && is_array($entry['file_map']) ? $entry['file_map'] : [];

            // Resolve rules for this structure
            $resolvedRules = $this->resolveStructureRules($entry, $config, $ruleRegistry);

            $sourceDir = $projectRoot . '/' . trim($sourcePath, '/');
            if (! is_dir($sourceDir)) {
                $this->warn("Source path does not exist: {$sourcePath}");
                continue;
            }

            $this->title("Structure: {$name}");
            $this->line("  <fg=gray>Source: {$sourcePath}</>");
            $this->line("  <fg=gray>Tests:  {$testPath}</>");

            $phpFiles = File::allFiles($sourceDir);
            $phpFiles = array_filter($phpFiles, fn ($f) => str_ends_with($f->getFilename(), '.php'));

            $fileRows = [];
            foreach ($phpFiles as $file) {
                $relativeSource = $namespaceHelper->normalizeRelativePath($file->getRelativePathname());
                $fullSourceRelative = trim($sourcePath, '/') . '/' . $relativeSource;
                $mappedTest = $fileMap[$relativeSource] ?? null;
                $expectedTestRelative = $mappedTest !== null
                    ? trim($testPath, '/') . '/' . $mappedTest
                    : $namespaceHelper->sourcePathToTestPath($fullSourceRelative, trim($sourcePath, '/'), trim($testPath, '/'));
                $expectedSourceFqcn = $namespaceHelper->pathToFqcn($fullSourceRelative);
                $testAbsolute = $projectRoot . '/' . $expectedTestRelative;
                $sourceAbsolute = $projectRoot . '/' . $fullSourceRelative;

                $testExists = is_file($testAbsolute);
                $testContent = $testExists ? @file_get_contents($testAbsolute) ?: null : null;

                $normalizedSource = realpath($sourceAbsolute) ?: $sourceAbsolute;
                $coveragePercent = $coverageMap[$normalizedSource] ?? $coverageMap[$fullSourceRelative] ?? 0.0;
                $coveringTests = $testsByFile[$normalizedSource] ?? $testsByFile[$fullSourceRelative] ?? [];

                // Compute matched coverage
                $expectedTestClass = pathinfo($expectedTestRelative, PATHINFO_FILENAME);
                $matchedCoveragePercent = null;
                if ($isPhpUnitXml && $testExists) {
                    $perLine = $lineCoverage[$normalizedSource] ?? $lineCoverage[$fullSourceRelative] ?? [];
                    $executable = $totalExecutable[$normalizedSource] ?? $totalExecutable[$fullSourceRelative] ?? 0;
                    if ($executable > 0 && $expectedTestClass !== '') {
                        $linesCoveredByMatch = 0;
                        foreach ($perLine as $lineTests) {
                            foreach ($lineTests as $testName) {
                                if (str_contains($testName, $expectedTestClass)) {
                                    $linesCoveredByMatch++;
                                    break;
                                }
                            }
                        }
                        $matchedCoveragePercent = round(100.0 * $linesCoveredByMatch / $executable, 2);
                    }
                }

                // Build rule context
                $context = new RuleContext(
                    sourceAbsolutePath: $normalizedSource,
                    sourceRelativePath: $fullSourceRelative,
                    expectedSourceFqcn: $expectedSourceFqcn,
                    testAbsolutePath: $testExists ? $testAbsolute : null,
                    testRelativePath: $testExists ? $expectedTestRelative : null,
                    testExists: $testExists,
                    testContent: $testContent,
                    coveragePercent: $coveragePercent,
                    matchedCoveragePercent: $matchedCoveragePercent,
                    coveringTests: $coveringTests,
                    projectRoot: $projectRoot,
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

                $otherTests = $expectedTestClass !== ''
                    ? array_values(array_filter($coveringTests, fn (string $t): bool => ! str_contains($t, $expectedTestClass)))
                    : $coveringTests;

                $expectedTestPaths[$expectedTestRelative] = true;
                $allFileCoverages[] = $coveragePercent;

                $dirUnderSource = dirname($relativeSource);
                $relativeTest = ($dirUnderSource === '.' ? '' : $dirUnderSource . '/') . basename($expectedTestRelative);

                $fileRows[] = [
                    'relativeSource' => $relativeSource,
                    'relativeTest' => $relativeTest,
                    'ruleResults' => $ruleResults,
                    'allPassed' => $allPassed,
                    'coveringTests' => $coveringTests,
                    'otherTests' => $otherTests,
                    'matchedCoveragePercent' => $matchedCoveragePercent,
                ];
            }

            // Build dynamic table
            $tableRows = $this->buildDynamicTableRows($fileRows, $resolvedRules, $isPhpUnitXml, $showTestNames);
            if ($tableRows !== []) {
                $this->newLine();
                $headers = $this->buildDynamicHeaders($resolvedRules, $isPhpUnitXml, $showTestNames);
                $this->table($headers, $tableRows);
                $this->newLine();
            }
        }

        // ── Summary ────────────────────────────────────────────────
        $minCoverageDefault = (float) ($config['min_coverage'] ?? 80);
        $minMatchedCoverageDefault = isset($config['min_matched_coverage']) ? (float) $config['min_matched_coverage'] : null;
        $perFileMin = $allFileCoverages !== [] ? min($allFileCoverages) : null;
        $perFileAvg = $allFileCoverages !== [] ? round(array_sum($allFileCoverages) / count($allFileCoverages), 2) : null;
        $this->title('Summary');
        $this->outputCoverageSummaryTable($globalPercent, $minCoverageGlobal, $minCoverageDefault, $minMatchedCoverageDefault, $perFileMin, $perFileAvg);

        if ($isPhpUnitXml && $testsByFile !== []) {
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
    private function resolveStructureRules(array $entry, array $globalConfig, RuleRegistry $registry): array
    {
        // New format: explicit rules array
        if (isset($entry['rules']) && is_array($entry['rules'])) {
            // Always prepend test-exists (implicit)
            $ruleConfigs = $entry['rules'];
            $hasTestExists = false;
            foreach ($ruleConfigs as $rc) {
                $name = is_string($rc) ? $rc : (isset($rc['name']) ? $rc['name'] : array_key_first($rc));
                if ($name === 'test-exists') {
                    $hasTestExists = true;
                    break;
                }
            }
            if (! $hasTestExists) {
                array_unshift($ruleConfigs, 'test-exists');
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
            : (float) ($globalConfig['min_coverage'] ?? 80);
        $ruleConfigs[] = ['minimum-coverage' => ['min' => $minCoverage]];

        return $registry->resolve($ruleConfigs);
    }

    // ── Dynamic table rendering ────────────────────────────────────

    /**
     * Build headers dynamically from resolved rules.
     */
    private function buildDynamicHeaders(array $resolvedRules, bool $isPhpUnitXml, bool $showTestNames): array
    {
        $headers = ['Source', 'Test'];

        foreach ($resolvedRules as $resolved) {
            $header = $resolved['rule']->columnHeader();
            if ($header !== null) {
                $headers[] = $header;
            }
        }

        $headers[] = 'OK';

        if ($isPhpUnitXml) {
            $headers[] = 'Match';
            $headers[] = $showTestNames ? 'Covered by' : '#';
            $headers[] = $showTestNames ? 'Other (non-matching)' : 'Other';
        }

        return $headers;
    }

    /**
     * Build table rows dynamically from rule results.
     */
    private function buildDynamicTableRows(array $fileRows, array $resolvedRules, bool $isPhpUnitXml, bool $showTestNames): array
    {
        if ($fileRows === []) {
            return [];
        }

        usort($fileRows, fn (array $a, array $b) => strcmp($a['relativeSource'], $b['relativeSource']));

        // Count how many rule columns there are
        $ruleColumnCount = 0;
        foreach ($resolvedRules as $resolved) {
            if ($resolved['rule']->columnHeader() !== null) {
                $ruleColumnCount++;
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
                    sprintf($gray, $dir . '/'),
                    sprintf($gray, $dir . '/'),
                ];
                for ($i = 0; $i < $ruleColumnCount; $i++) {
                    $dirRow[] = sprintf($gray, '-');
                }
                $dirRow[] = sprintf($gray, '-'); // OK column
                if ($isPhpUnitXml) {
                    $dirRow[] = sprintf($gray, '-');
                    $dirRow[] = sprintf($gray, '-');
                    $dirRow[] = sprintf($gray, '-');
                }
                $rows[] = $dirRow;
            }
            if ($dir === '.') {
                $lastDir = $dir;
            }

            $sourceCell = ($dir === '.' ? '' : '  ') . basename($row['relativeSource']);
            $testCell = ($dir === '.' ? '' : '  ') . basename($row['relativeTest']);

            $tableRow = [$sourceCell, $testCell];

            // Dynamic rule columns
            foreach ($row['ruleResults'] as $rr) {
                /** @var RuleInterface $rule */
                $rule = $rr['rule'];
                if ($rule->columnHeader() !== null) {
                    $tableRow[] = $rule->formatCell($rr['result']);
                }
            }

            // OK column
            $tableRow[] = $row['allPassed'] ? '<fg=green>Y</>' : '<fg=red>N</>';

            // PHPUnit XML extra columns
            if ($isPhpUnitXml) {
                $tableRow[] = $this->styleMatch($row['matchedCoveragePercent'] ?? null);
                $tableRow[] = $this->formatCoveringTests($row['coveringTests'], $showTestNames);
                $tableRow[] = $this->formatOtherTests($row['otherTests'], $showTestNames);
            }

            $rows[] = $tableRow;
        }

        return $rows;
    }

    // ── Coverage data loading ──────────────────────────────────────

    private function loadCoverageData(array $config, string $projectRoot): ?array
    {
        $coverageXmlConfig = $config['coverage_xml'] ?? ['clover.xml', 'coverage.xml'];
        $coverageCandidates = is_array($coverageXmlConfig) ? $coverageXmlConfig : [$coverageXmlConfig];
        $minCoverageGlobal = isset($config['min_coverage_global']) ? (float) $config['min_coverage_global'] : null;

        $coveragePath = null;
        $isPhpUnitXml = false;
        foreach ($coverageCandidates as $candidate) {
            $path = $projectRoot . '/' . ltrim((string) $candidate, '/');
            if (is_file($path)) {
                $coveragePath = $path;
                break;
            }
            if (is_dir($path) && is_file($path . '/index.xml')) {
                $coveragePath = $path;
                $isPhpUnitXml = true;
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

        if ($isPhpUnitXml) {
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

        return compact('coverageMap', 'testsByFile', 'lineCoverage', 'totalExecutable', 'globalPercent', 'isPhpUnitXml');
    }

    // ── Formatting helpers ─────────────────────────────────────────

    private function styleMatch(?float $percent): string
    {
        if ($percent === null) {
            return '<fg=gray>-</>';
        }

        return $percent . '%';
    }

    private function formatCoveringTests(array $coveringTests, bool $showNames = false): string
    {
        if ($coveringTests === []) {
            return '<fg=gray>-</>';
        }
        if (! $showNames) {
            return (string) count($coveringTests);
        }
        $maxShow = 3;
        $shown = array_slice($coveringTests, 0, $maxShow);
        $text = implode(', ', $shown);
        $rest = count($coveringTests) - $maxShow;
        if ($rest > 0) {
            $text .= ' <fg=gray>(+' . $rest . ')</>';
        }

        return $text;
    }

    private function formatOtherTests(array $otherTests, bool $showNames = false): string
    {
        if ($otherTests === []) {
            return '<fg=gray>-</>';
        }
        $n = count($otherTests);
        if (! $showNames) {
            return '<fg=yellow>' . $n . '</>';
        }
        $maxShow = 3;
        $shown = array_slice($otherTests, 0, $maxShow);
        $text = implode(', ', $shown);
        $rest = $n - $maxShow;
        if ($rest > 0) {
            $text .= ' <fg=gray>(+' . $rest . ')</>';
        }

        return '<fg=yellow>' . $text . '</>';
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
            $this->line('  <fg=yellow>' . $path . '</>');
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

        return implode('/', $segments) . '.php';
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
        if (! is_file($cwd . '/parity.yaml')) {
            return null;
        }

        return realpath($cwd) ?: $cwd;
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
            $config = Yaml::parse($contents);

            return is_array($config) ? $config : null;
        }
        $this->error('Install symfony/yaml to use parity.yaml, or use a PHP config.');

        return null;
    }
}
