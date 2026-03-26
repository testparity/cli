<?php

declare(strict_types=1);

namespace App\Commands;

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

    protected $description = 'Check structural parity (CoversClass) and code coverage per file; does not run tests';

    public function handle(NamespaceHelper $namespaceHelper): int
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

        $coverageXmlConfig = $config['coverage_xml'] ?? ['clover.xml', 'coverage.xml'];
        $coverageCandidates = is_array($coverageXmlConfig)
            ? $coverageXmlConfig
            : [$coverageXmlConfig];
        $minCoverageDefault = (float) ($config['min_coverage'] ?? 80);
        $minCoverageGlobal = isset($config['min_coverage_global']) ? (float) $config['min_coverage_global'] : null;
        $minMatchedCoverageDefault = isset($config['min_matched_coverage']) ? (float) $config['min_matched_coverage'] : null;

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
            $this->error("No coverage file or directory found (tried: {$tried}). Run tests with coverage (e.g. --coverage-clover clover.xml or --coverage-xml coverage-xml).");

            return self::FAILURE;
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

        if ($minCoverageGlobal !== null && $globalPercent !== null) {
            if ($globalPercent >= $minCoverageGlobal) {
                $this->info("Global coverage: {$globalPercent}% (minimum: {$minCoverageGlobal}%).");
            } else {
                $this->error("Global coverage {$globalPercent}% is below minimum {$minCoverageGlobal}%.");
            }
        } elseif ($minCoverageGlobal !== null && $globalPercent === null && ! $isPhpUnitXml) {
            $this->warn('Could not read global coverage from coverage file.');
        }

        $structures = $config['structure'] ?? [];
        if ($structures === []) {
            $this->outputCoverageSummaryTable($globalPercent, $minCoverageGlobal, $minCoverageDefault, $minMatchedCoverageDefault);
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
            $sourcePath = $entry['source_path'] ?? '';
            $testPath = $entry['test_path'] ?? '';
            $enforceAttribute = $entry['enforce_attribute'] ?? 'PHPUnit\Framework\Attributes\CoversClass';
            $minCoverage = isset($entry['min_coverage']) ? (float) $entry['min_coverage'] : $minCoverageDefault;
            $minMatchedCoverage = isset($entry['min_matched_coverage']) ? (float) $entry['min_matched_coverage'] : $minMatchedCoverageDefault;
            $fileMap = isset($entry['file_map']) && is_array($entry['file_map']) ? $entry['file_map'] : [];

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
                $relativeSource = $namespaceHelper->normalizeRelativePath(
                    $file->getRelativePathname()
                );
                $fullSourceRelative = trim($sourcePath, '/') . '/' . $relativeSource;
                $mappedTest = $fileMap[$relativeSource] ?? null;
                $expectedTestRelative = $mappedTest !== null
                    ? trim($testPath, '/') . '/' . $mappedTest
                    : $namespaceHelper->sourcePathToTestPath(
                        $fullSourceRelative,
                        trim($sourcePath, '/'),
                        trim($testPath, '/')
                    );
                $expectedSourceFqcn = $namespaceHelper->pathToFqcn($fullSourceRelative);
                $testAbsolute = $projectRoot . '/' . $expectedTestRelative;
                $sourceAbsolute = $projectRoot . '/' . $fullSourceRelative;

                $testExists = is_file($testAbsolute);
                $attributeValid = false;

                if ($testExists) {
                    $result = $checker->validateTestAttribute(
                        $testAbsolute,
                        $expectedSourceFqcn,
                        $enforceAttribute
                    );
                    $attributeValid = $result['valid'];
                }

                $normalizedSource = realpath($sourceAbsolute) ?: $sourceAbsolute;
                $coveragePercent = $coverageMap[$normalizedSource]
                    ?? $coverageMap[$fullSourceRelative]
                    ?? 0.0;
                $coverageOk = $coveragePercent >= $minCoverage;
                $coveringTests = $testsByFile[$normalizedSource]
                    ?? $testsByFile[$fullSourceRelative]
                    ?? [];

                $expectedTestClass = pathinfo($expectedTestRelative, PATHINFO_FILENAME);
                $otherTests = $expectedTestClass !== ''
                    ? array_values(array_filter($coveringTests, fn (string $t): bool => ! str_contains($t, $expectedTestClass)))
                    : $coveringTests;

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
                $matchedCoverageOk = $minMatchedCoverage !== null && $matchedCoveragePercent !== null
                    ? $matchedCoveragePercent >= $minMatchedCoverage
                    : null;

                $taskOk = $testExists && $attributeValid && $coverageOk;
                if ($matchedCoverageOk === false || ! $taskOk) {
                    $hasFailure = true;
                }

                $dirUnderSource = dirname($relativeSource);
                $relativeTest = ($dirUnderSource === '.' ? '' : $dirUnderSource . '/') . basename($expectedTestRelative);

                $expectedTestPaths[$expectedTestRelative] = true;
                $allFileCoverages[] = $coveragePercent;

                $fileRows[] = [
                    'relativeSource' => $relativeSource,
                    'relativeTest' => $relativeTest,
                    'testBasename' => basename($expectedTestRelative),
                    'testExists' => $testExists,
                    'attributeValid' => $attributeValid,
                    'coveragePercent' => $coveragePercent,
                    'coverageOk' => $coverageOk,
                    'matchedCoveragePercent' => $matchedCoveragePercent,
                    'matchedCoverageOk' => $matchedCoverageOk,
                    'coveringTests' => $coveringTests,
                    'otherTests' => $otherTests,
                ];
            }

            $structureRows = $this->buildTreeTableRows($fileRows, $isPhpUnitXml, $showTestNames);

            if ($structureRows !== []) {
                $this->newLine();
                $headers = ['Source', 'Test', '∃', 'Attr', 'Cov', 'OK'];
                if ($isPhpUnitXml) {
                    array_splice($headers, 5, 0, ['Match']);
                    $headers[] = $showTestNames ? 'Covered by' : '#';
                    $headers[] = $showTestNames ? 'Other (non-matching)' : 'Other';
                }
                $this->table($headers, $structureRows);
                $this->newLine();
            }
        }

        $perFileMin = $allFileCoverages !== [] ? min($allFileCoverages) : null;
        $perFileAvg = $allFileCoverages !== [] ? round(array_sum($allFileCoverages) / count($allFileCoverages), 2) : null;
        $this->title('Summary');
        $this->outputCoverageSummaryTable($globalPercent, $minCoverageGlobal, $minCoverageDefault, $minMatchedCoverageDefault, $perFileMin, $perFileAvg);

        if ($isPhpUnitXml && $testsByFile !== []) {
            $this->warnTestsNotMatchingAnyStructure($testsByFile, $expectedTestPaths, $namespaceHelper);
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Build table rows with folder trees for source and test paths.
     *
     * @param array<int, array{relativeSource: string, relativeTest: string, testBasename: string, testExists: bool, attributeValid: bool, coveragePercent: float, coverageOk: bool, matchedCoveragePercent: float|null, matchedCoverageOk: bool|null, coveringTests: list<string>, otherTests: list<string>}> $fileRows
     * @return array<int, array<int, string>>
     */
    private function buildTreeTableRows(array $fileRows, bool $showCoveredBy = false, bool $showTestNames = false): array
    {
        if ($fileRows === []) {
            return [];
        }

        usort($fileRows, fn (array $a, array $b) => strcmp($a['relativeSource'], $b['relativeSource']));

        $rows = [];
        $lastDir = null;
        $gray = '<fg=gray>%s</>';

        foreach ($fileRows as $row) {
            $relativeSource = $row['relativeSource'];
            $relativeTest = $row['relativeTest'];
            $coveringTests = $row['coveringTests'] ?? [];
            $dir = dirname($relativeSource);
            $sourceBasename = basename($relativeSource);
            $testBasename = basename($relativeTest);

            if ($dir !== $lastDir && $dir !== '.') {
                $lastDir = $dir;
                $dirRow = [
                    sprintf($gray, $dir . '/'),
                    sprintf($gray, $dir . '/'),
                    sprintf($gray, '-'),
                    sprintf($gray, '-'),
                    sprintf($gray, '-'),
                    sprintf($gray, '-'),
                ];
                if ($showCoveredBy) {
                    $dirRow[] = sprintf($gray, '-');
                    $dirRow[] = sprintf($gray, '-');
                    $dirRow[] = sprintf($gray, '-');
                }
                $rows[] = $dirRow;
            }
            if ($dir === '.') {
                $lastDir = $dir;
            }

            $sourceTreeCell = ($dir === '.' ? '' : '  ') . $sourceBasename;
            $testTreeCell = ($dir === '.' ? '' : '  ') . $testBasename;
            $fileRow = [
                $sourceTreeCell,
                $testTreeCell,
                $this->styleExists($row['testExists']),
                $this->styleAttr($row['testExists'], $row['attributeValid']),
                $this->styleCov($row['coveragePercent'], $row['coverageOk']),
                $this->styleOk($row['coverageOk']),
            ];
            if ($showCoveredBy) {
                $otherTests = $row['otherTests'] ?? [];
                array_splice($fileRow, 5, 0, [$this->styleMatch($row['matchedCoveragePercent'] ?? null, $row['matchedCoverageOk'] ?? null)]);
                $fileRow[] = $this->formatCoveringTests($coveringTests, $showTestNames);
                $fileRow[] = $this->formatOtherTests($otherTests, $showTestNames);
            }
            $rows[] = $fileRow;
        }

        return $rows;
    }

    /** Required + passed = green, required + failed = red. */
    private function styleExists(bool $testExists): string
    {
        return $testExists ? '<fg=green>Y</>' : '<fg=red>N</>';
    }

    /** Required + passed = green, required + failed = red, not required = gray. */
    private function styleAttr(bool $testExists, bool $attributeValid): string
    {
        if (! $testExists) {
            return '<fg=gray>-</>';
        }
        return $attributeValid ? '<fg=green>Y</>' : '<fg=red>N</>';
    }

    /** Coverage %: green if OK, red if not. */
    private function styleCov(float $percent, bool $ok): string
    {
        $color = $ok ? 'green' : 'red';
        return "<fg={$color}>{$percent}%</>";
    }

    /** Match column: coverage % from matching test file only; green/red when enforced, gray "-" when N/A. */
    private function styleMatch(?float $percent, ?bool $ok = null): string
    {
        if ($percent === null) {
            return '<fg=gray>-</>';
        }
        if ($ok !== null) {
            $color = $ok ? 'green' : 'red';
            return "<fg={$color}>{$percent}%</>";
        }
        return $percent . '%';
    }

    /** Required + passed = green, required + failed = red. */
    private function styleOk(bool $ok): string
    {
        return $ok ? '<fg=green>Y</>' : '<fg=red>N</>';
    }

    /**
     * Format covering tests for the table: count only (narrow) or truncated list of names (when --show-tests).
     *
     * @param list<string> $coveringTests
     */
    private function formatCoveringTests(array $coveringTests, bool $showNames = false): string
    {
        if ($coveringTests === []) {
            return '<fg=gray>-</>';
        }
        if (! $showNames) {
            $n = count($coveringTests);
            return $n === 1 ? '1' : (string) $n;
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

    /**
     * Format "other" (non-matching) tests: tests that cover this file but are not from the matching test file.
     * Count by default; yellow when > 0 to warn; with --show-tests show truncated list.
     *
     * @param list<string> $otherTests
     */
    private function formatOtherTests(array $otherTests, bool $showNames = false): string
    {
        if ($otherTests === []) {
            return '<fg=gray>-</>';
        }
        $n = count($otherTests);
        if (! $showNames) {
            return '<fg=yellow>' . ($n === 1 ? '1' : (string) $n) . '</>';
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

    /**
     * After all structures: list test files that appear in coverage but do not match any structure (e.g. wrong namespace).
     *
     * @param array<string, list<string>> $testsByFile
     * @param array<string, true> $expectedTestPaths
     */
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
            $path = $this->testClassToRelativePath($class, $namespaceHelper);
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

    /**
     * Convert test class FQCN to relative path (e.g. Tests\Unit\... → tests/Unit/....php).
     */
    private function testClassToRelativePath(string $testClass, NamespaceHelper $namespaceHelper): string
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
        $first = $segments[0];
        if (strtolower($first) === 'tests') {
            $segments[0] = 'tests';
        }
        return implode('/', $segments) . '.php';
    }

    /**
     * Output a small table showing global coverage (value + required), per-file min, and match min when set.
     */
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
        $configPath = $cwd . '/parity.yaml';
        if (! is_file($configPath)) {
            return null;
        }

        return realpath($cwd) ?: $cwd;
    }

    /**
     * @return array{structure: array}|null
     */
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
        // Fallback: parse minimal YAML or suggest symfony/yaml
        $this->error('Install symfony/yaml to use parity.yaml, or use a PHP config.');

        return null;
    }
}
