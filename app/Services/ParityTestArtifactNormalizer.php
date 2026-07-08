<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Specs: S003
 *
 * Normalizes a single-test coverage artifact into Parity's per-test coverage report shape.
 */
class ParityTestArtifactNormalizer
{
    /**
     * @return array{
     *     version: int,
     *     test: string,
     *     files: list<array{path: string, totalExecutableLines: int, coveredLines: list<int>}>
     * }
     */
    public function normalize(string $coveragePath, string $testName, string $projectRoot): array
    {
        if (is_dir($coveragePath) && is_file(rtrim($coveragePath, '/\\').'/index.xml')) {
            $reader = new PhpUnitXmlCoverageReader;
            $result = $reader->read($coveragePath, $projectRoot);

            return $this->buildReport($result['lineCoverage'] ?? [], $result['totalExecutable'] ?? [], $testName, $projectRoot);
        }

        if (is_file($coveragePath) && strtolower(pathinfo($coveragePath, PATHINFO_EXTENSION)) === 'json') {
            $reader = new ParityJsonCoverageReader;
            $result = $reader->read($coveragePath, $projectRoot);

            return $this->buildReport($result['lineCoverage'] ?? [], $result['totalExecutable'] ?? [], $testName, $projectRoot);
        }

        $reader = new CoverageReader;
        $result = $reader->readExecutableLines($coveragePath, $projectRoot);
        $lineCoverage = [];
        foreach ($result['coveredLines'] as $path => $lines) {
            foreach ($lines as $line) {
                $lineCoverage[$path][$line] = [$testName];
            }
        }

        return $this->buildReport($lineCoverage, $result['totalExecutable'] ?? [], $testName, $projectRoot);
    }

    /**
     * @param  array<string, array<int, list<string>>>  $lineCoverage
     * @param  array<string, int>  $totalExecutable
     * @return array{
     *     version: int,
     *     test: string,
     *     files: list<array{path: string, totalExecutableLines: int, coveredLines: list<int>}>
     * }
     */
    private function buildReport(array $lineCoverage, array $totalExecutable, string $testName, string $projectRoot): array
    {
        $files = [];
        $root = str_replace('\\', '/', $projectRoot);
        $realRoot = realpath($projectRoot);
        if ($realRoot !== false) {
            $root = str_replace('\\', '/', $realRoot);
        }
        $root = rtrim($root, '/');
        $keys = array_unique(array_merge(array_keys($lineCoverage), array_keys($totalExecutable)));
        sort($keys);

        foreach ($keys as $path) {
            if (str_starts_with($path, $root.'/')) {
                continue;
            }

            $coveredLines = isset($lineCoverage[$path]) ? array_map('intval', array_keys($lineCoverage[$path])) : [];
            sort($coveredLines);

            $files[] = [
                'path' => $path,
                'totalExecutableLines' => (int) ($totalExecutable[$path] ?? 0),
                'coveredLines' => $coveredLines,
            ];
        }

        return [
            'version' => 1,
            'test' => $testName,
            'files' => $files,
        ];
    }
}
