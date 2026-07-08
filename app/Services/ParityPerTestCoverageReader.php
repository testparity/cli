<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Specs: S003
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
        if (! is_array($index)) {
            return $empty;
        }

        $coverage = [];
        $testsByFile = [];
        $lineCoverage = [];
        $totalExecutable = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;

        foreach (($index['reports'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $testName = isset($entry['test']) && is_string($entry['test']) ? $entry['test'] : '';
            $reportRel = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : '';
            if ($testName === '' || $reportRel === '') {
                continue;
            }

            $reportPath = rtrim($dirPath, '/\\').'/'.ltrim($reportRel, '/');
            if (! is_file($reportPath)) {
                continue;
            }

            $reportContent = @file_get_contents($reportPath);
            if ($reportContent === false) {
                continue;
            }

            $report = json_decode($reportContent, true);
            if (! is_array($report)) {
                continue;
            }

            foreach (($report['files'] ?? []) as $file) {
                if (! is_array($file) || ! isset($file['path']) || ! is_string($file['path'])) {
                    continue;
                }

                $relativePath = ltrim(str_replace('\\', '/', $file['path']), '/');
                $coveredLines = array_values(array_unique(array_map('intval', is_array($file['coveredLines'] ?? null) ? $file['coveredLines'] : [])));
                sort($coveredLines);
                $executable = isset($file['totalExecutableLines']) && is_numeric($file['totalExecutableLines'])
                    ? (int) $file['totalExecutableLines']
                    : 0;

                $keys = [$relativePath];
                if ($root !== null) {
                    $keys[] = $this->normalizePath($root.'/'.$relativePath);
                }

                foreach ($keys as $key) {
                    $totalExecutable[$key] = max($totalExecutable[$key] ?? 0, $executable);
                    $testsByFile[$key] ??= [];
                    if (! in_array($testName, $testsByFile[$key], true)) {
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

        return $real !== false ? $real : $path;
    }
}
