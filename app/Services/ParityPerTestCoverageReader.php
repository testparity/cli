<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Specs: S003, S011
 *
 * Reads Parity's directory-based per-test coverage format.
 */
class ParityPerTestCoverageReader
{
    /**
     * @return array{
     *     coverage: array<string, float>,
     *     testsByFile: array<string, list<string>>,
     *     lineCoverage: array<string, array<int, list<string>>>,
     *     totalExecutable: array<string, int>,
     *     globalPercent: float|null
     * }
     */
    public function read(string $dirPath, ?string $projectRoot = null): array
    {
        $empty = ['coverage' => [], 'testsByFile' => [], 'lineCoverage' => [], 'totalExecutable' => [], 'globalPercent' => null];
        $indexPath = rtrim($dirPath, '/\\').'/index.json';
        if (! is_file($indexPath)) {
            return $empty;
        }

        $indexContent = @file_get_contents($indexPath);
        if ($indexContent === false) {
            return $empty;
        }

        $index = json_decode($indexContent, true);
        if (! is_array($index)
            || ($index['version'] ?? null) !== 1
            || ($index['kind'] ?? null) !== 'parity-per-test-coverage'
            || ! is_array($index['reports'] ?? null)) {
            return $empty;
        }

        $coverage = [];
        $testsByFile = [];
        $lineCoverage = [];
        $totalExecutable = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;
        $baseRoot = realpath($dirPath);
        $reportsConfiguredPath = rtrim($dirPath, '/\\').'/reports';
        $reportsRoot = realpath($reportsConfiguredPath);
        if ($baseRoot === false || $reportsRoot === false || is_link($reportsConfiguredPath)) {
            return $empty;
        }
        $baseRoot = $this->normalizePath($baseRoot);
        $reportsRoot = $this->normalizePath($reportsRoot);
        if (! $this->isPathWithin($reportsRoot, $baseRoot)) {
            return $empty;
        }

        foreach ($index['reports'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $testName = isset($entry['test']) && is_string($entry['test']) ? $entry['test'] : '';
            $reportRel = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : '';
            if ($testName === '' || $reportRel === '') {
                continue;
            }

            $reportRelativePath = $this->normalizeRelativePath($reportRel);
            if ($reportRelativePath === null || ! str_starts_with($reportRelativePath, 'reports/')) {
                continue;
            }

            $reportPath = realpath(rtrim($dirPath, '/\\').'/'.$reportRelativePath);
            if ($reportPath === false || ! is_file($reportPath)) {
                continue;
            }
            $reportPath = $this->normalizePath($reportPath);
            if (! $this->isPathWithin($reportPath, $reportsRoot)) {
                continue;
            }

            $reportContent = @file_get_contents($reportPath);
            if ($reportContent === false) {
                continue;
            }

            $report = json_decode($reportContent, true);
            if (! is_array($report)
                || ($report['version'] ?? null) !== 1
                || ($report['test'] ?? null) !== $testName
                || ! is_array($report['files'] ?? null)) {
                continue;
            }

            foreach ($report['files'] as $file) {
                if (! is_array($file) || ! isset($file['path']) || ! is_string($file['path'])) {
                    continue;
                }

                $relativePath = $this->normalizeRelativePath($file['path']);
                if ($relativePath === null) {
                    continue;
                }

                $coveredLines = array_values(array_unique(array_filter(
                    array_map('intval', is_array($file['coveredLines'] ?? null) ? $file['coveredLines'] : []),
                    fn (int $line): bool => $line > 0
                )));
                sort($coveredLines);
                $executable = isset($file['totalExecutableLines']) && is_numeric($file['totalExecutableLines'])
                    ? max(0, (int) $file['totalExecutableLines'])
                    : 0;
                if ($executable === 0 || count($coveredLines) > $executable) {
                    continue;
                }

                $keys = [$relativePath];
                if ($root !== null) {
                    $keys[] = $this->normalizePath($root.'/'.$relativePath);
                }

                foreach ($keys as $key) {
                    $totalExecutable[$key] = max($totalExecutable[$key] ?? 0, $executable);
                    $testsByFile[$key] ??= [];
                    if ($coveredLines !== [] && ! in_array($testName, $testsByFile[$key], true)) {
                        $testsByFile[$key][] = $testName;
                    }
                    $lineCoverage[$key] ??= [];
                    foreach ($coveredLines as $line) {
                        $lineCoverage[$key][$line] ??= [];
                        if (! in_array($testName, $lineCoverage[$key][$line], true)) {
                            $lineCoverage[$key][$line][] = $testName;
                        }
                    }
                }
            }
        }

        $allKeys = array_unique(array_merge(array_keys($lineCoverage), array_keys($totalExecutable)));
        foreach ($allKeys as $key) {
            $covered = isset($lineCoverage[$key]) ? count($lineCoverage[$key]) : 0;
            $executable = $totalExecutable[$key] ?? 0;
            $coverage[$key] = $executable > 0 ? round(100.0 * $covered / $executable, 2) : 100.0;
        }

        $globalPercent = $this->readGlobalPercent($lineCoverage, $totalExecutable, $root);

        return [
            'coverage' => $coverage,
            'testsByFile' => $testsByFile,
            'lineCoverage' => $lineCoverage,
            'totalExecutable' => $totalExecutable,
            'globalPercent' => $globalPercent,
        ];
    }

    /** @param array<string, array<int, list<string>>> $lineCoverage @param array<string, int> $totalExecutable */
    private function readGlobalPercent(array $lineCoverage, array $totalExecutable, ?string $root): ?float
    {
        $keys = array_keys($totalExecutable);
        if ($root !== null) {
            $keys = array_values(array_filter($keys, fn (string $key): bool => ! str_starts_with($key, $root.'/')));
        }

        if ($keys === []) {
            return null;
        }

        $covered = 0;
        $executable = 0;
        foreach ($keys as $key) {
            $covered += isset($lineCoverage[$key]) ? count($lineCoverage[$key]) : 0;
            $executable += $totalExecutable[$key] ?? 0;
        }

        if ($executable === 0) {
            return 100.0;
        }

        return round(100.0 * $covered / $executable, 2);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);

        return $real !== false ? str_replace('\\', '/', $real) : $path;
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    private function isPathWithin(string $path, string $parent): bool
    {
        $path = rtrim($path, '/');
        $parent = rtrim($parent, '/');

        return $path !== $parent && str_starts_with($path, $parent.'/');
    }
}
