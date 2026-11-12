<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Specs: S003, S011
 *
 * Normalizes a single-test coverage artifact into Parity's per-test coverage report shape.
 */
class ParityTestArtifactNormalizer
{
    /**
     * @return array{
     *     version: int,
     *     test: string,
     *     files: list<array{path: string, totalExecutableLines: int, coveredLines: list<int>, executableLines?: list<int>}>
     * }
     */
    public function normalize(string $coveragePath, string $testName, string $projectRoot): array
    {
        if (is_dir($coveragePath) && is_file(rtrim($coveragePath, '/\\').'/index.xml')) {
            $reader = new PhpUnitXmlCoverageReader;
            $result = $reader->read($coveragePath, $projectRoot);

            return $this->buildReport($result['lineCoverage'] ?? [], $result['totalExecutable'] ?? [], $result['executableLines'] ?? [], $testName, $projectRoot);
        }

        if (is_file($coveragePath) && strtolower(pathinfo($coveragePath, PATHINFO_EXTENSION)) === 'json') {
            $reader = new ParityJsonCoverageReader;
            $result = $reader->read($coveragePath, $projectRoot);

            return $this->buildReport($result['lineCoverage'] ?? [], $result['totalExecutable'] ?? [], $result['executableLines'] ?? [], $testName, $projectRoot);
        }

        $reader = new CoverageReader;
        $result = $reader->readExecutableLines($coveragePath, $projectRoot);
        $lineCoverage = [];
        foreach ($result['coveredLines'] as $path => $lines) {
            foreach ($lines as $line) {
                $lineCoverage[$path][$line] = [$testName];
            }
        }

        return $this->buildReport($lineCoverage, $result['totalExecutable'] ?? [], $result['executableLines'] ?? [], $testName, $projectRoot);
    }

    /**
     * @param  array<string, array<int, list<string>>>  $lineCoverage
     * @param  array<string, int>  $totalExecutable
     * @param  array<string, list<int>>  $executableByPath
     * @return array{
     *     version: int,
     *     test: string,
     *     files: list<array{path: string, totalExecutableLines: int, coveredLines: list<int>, executableLines?: list<int>}>
     * }
     */
    private function buildReport(array $lineCoverage, array $totalExecutable, array $executableByPath, string $testName, string $projectRoot): array
    {
        $filesByPath = [];
        $root = str_replace('\\', '/', $projectRoot);
        $realRoot = realpath($projectRoot);
        if ($realRoot !== false) {
            $root = str_replace('\\', '/', $realRoot);
        }
        $root = rtrim($root, '/');
        $keys = array_unique(array_merge(array_keys($lineCoverage), array_keys($totalExecutable)));
        sort($keys);

        foreach ($keys as $path) {
            $relativePath = $this->relativeCoveragePath((string) $path, $root);
            if ($relativePath === null) {
                continue;
            }

            $executable = max(0, (int) ($totalExecutable[$path] ?? 0));
            if ($executable === 0 && ! array_key_exists($path, $executableByPath)) {
                continue;
            }

            $coveredLines = isset($lineCoverage[$path])
                ? array_values(array_filter(array_map('intval', array_keys($lineCoverage[$path])), fn (int $line): bool => $line > 0))
                : [];
            sort($coveredLines);

            $filesByPath[$relativePath] ??= [
                'path' => $relativePath,
                'totalExecutableLines' => 0,
                'coveredLines' => [],
            ];
            $filesByPath[$relativePath]['totalExecutableLines'] = max(
                $filesByPath[$relativePath]['totalExecutableLines'],
                $executable
            );
            $filesByPath[$relativePath]['coveredLines'] = array_values(array_unique(array_merge(
                $filesByPath[$relativePath]['coveredLines'],
                $coveredLines
            )));
            sort($filesByPath[$relativePath]['coveredLines']);

            if (isset($executableByPath[$path])) {
                $lines = array_values(array_unique(array_merge(
                    $filesByPath[$relativePath]['executableLines'] ?? [],
                    $executableByPath[$path]
                )));
                sort($lines);
                $filesByPath[$relativePath]['executableLines'] = $lines;
            }
        }

        ksort($filesByPath);

        return [
            'version' => 1,
            'test' => $testName,
            'files' => array_values($filesByPath),
        ];
    }

    private function relativeCoveragePath(string $path, string $projectRoot): ?string
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, $projectRoot.'/')) {
            $path = substr($path, strlen($projectRoot) + 1);
        } elseif (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== '' && $segment !== '.'));
        if ($segments === [] || in_array('..', $segments, true)) {
            return null;
        }

        return implode('/', $segments);
    }
}
