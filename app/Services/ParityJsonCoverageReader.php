<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Specs: S003
 *
 * Reads Parity's language-neutral JSON attribution format.
 */
class ParityJsonCoverageReader
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
    public function read(string $path, ?string $projectRoot = null): array
    {
        $empty = ['coverage' => [], 'testsByFile' => [], 'lineCoverage' => [], 'totalExecutable' => [], 'globalPercent' => null];

        if (! is_file($path)) {
            return $empty;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $empty;
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return $empty;
        }

        $coverage = [];
        $testsByFile = [];
        $lineCoverage = [];
        $totalExecutable = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;

        foreach (($data['files'] ?? []) as $file) {
            if (! is_array($file) || ! isset($file['path']) || ! is_string($file['path'])) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', $file['path']), '/');
            $percent = isset($file['coveragePercent']) && is_numeric($file['coveragePercent'])
                ? (float) $file['coveragePercent']
                : $this->calculatePercent($file);
            $executable = isset($file['totalExecutableLines']) && is_numeric($file['totalExecutableLines'])
                ? (int) $file['totalExecutableLines']
                : $this->countExecutableLines($file);
            $perLine = $this->readLineCoverage($file);
            $tests = $this->uniqueTests($perLine);

            $this->store($coverage, $relativePath, $percent, $root);
            $this->store($testsByFile, $relativePath, $tests, $root);
            $this->store($lineCoverage, $relativePath, $perLine, $root);
            $this->store($totalExecutable, $relativePath, $executable, $root);
        }

        return [
            'coverage' => $coverage,
            'testsByFile' => $testsByFile,
            'lineCoverage' => $lineCoverage,
            'totalExecutable' => $totalExecutable,
            'globalPercent' => isset($data['globalPercent']) && is_numeric($data['globalPercent']) ? (float) $data['globalPercent'] : null,
        ];
    }

    private function calculatePercent(array $file): float
    {
        $executable = $this->countExecutableLines($file);
        if ($executable === 0) {
            return 100.0;
        }

        $covered = count($this->readLineCoverage($file));

        return round(100.0 * $covered / $executable, 2);
    }

    private function countExecutableLines(array $file): int
    {
        $lines = $file['lines'] ?? [];

        return is_array($lines) ? count($lines) : 0;
    }

    /** @return array<int, list<string>> */
    private function readLineCoverage(array $file): array
    {
        $perLine = [];
        $lines = $file['lines'] ?? [];
        if (! is_array($lines)) {
            return [];
        }

        foreach ($lines as $line) {
            if (! is_array($line) || ! isset($line['line']) || ! is_numeric($line['line'])) {
                continue;
            }

            $coveredBy = $line['coveredBy'] ?? [];
            if (! is_array($coveredBy)) {
                continue;
            }

            $tests = array_values(array_filter($coveredBy, 'is_string'));
            if ($tests !== []) {
                $perLine[(int) $line['line']] = $tests;
            }
        }

        ksort($perLine);

        return $perLine;
    }

    /** @param array<int, list<string>> $perLine */
    private function uniqueTests(array $perLine): array
    {
        $tests = [];
        foreach ($perLine as $lineTests) {
            foreach ($lineTests as $test) {
                $tests[$test] = true;
            }
        }

        return array_keys($tests);
    }

    private function store(array &$target, string $relativePath, mixed $value, ?string $root): void
    {
        $target[$relativePath] = $value;

        if ($root === null) {
            return;
        }

        $target[$this->normalizePath($root.'/'.$relativePath)] = $value;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }
}
