<?php

declare(strict_types=1);

namespace App\Services;

final class SuiteCoverageComparison
{
    private readonly array $suite;

    public function __construct(string $path, string $projectRoot)
    {
        $absolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
        $this->suite = (new CoverageReader)->readExecutableLines($absolute ? $path : $projectRoot.'/'.$path, $projectRoot);

        if ($this->suite['executableLines'] === []) {
            throw new \InvalidArgumentException('No file-level coverage data found in --suite-coverage. Supply the full-suite Clover XML report.');
        }
    }

    public function hasExecutableLines(string $absolutePath, string $relativePath): bool
    {
        return $this->executableLines($absolutePath, $relativePath) !== [];
    }

    /** @param list<int> $lines */
    public static function lineSignature(array $lines): string
    {
        sort($lines, SORT_NUMERIC);

        return hash('sha256', implode(',', $lines), true);
    }

    /** @return array{suite: ?float, associated: ?float, gap: ?float} */
    public function compare(string $absolutePath, string $relativePath, string $testIdentifier, ?string $testLineSignature, array $testCoverage): array
    {
        $executable = $this->executableLines($absolutePath, $relativePath);
        $total = count($executable);
        $result = ['suite' => null, 'associated' => null, 'gap' => null];
        if ($total === 0) {
            return $result;
        }

        $suiteCovered = $this->suite['coveredLines'][$absolutePath] ?? $this->suite['coveredLines'][$relativePath] ?? [];
        $result['suite'] = round(100 * count($suiteCovered) / $total, 2);

        if ($testLineSignature !== self::lineSignature($executable)) {
            return $result;
        }

        $associated = array_keys(array_filter($testCoverage, static fn (array $tests): bool => in_array($testIdentifier, $tests, true)));
        if (array_diff($associated, $suiteCovered) !== []) {
            return $result;
        }

        $result['associated'] = round(100 * count($associated) / $total, 2);
        $result['gap'] = round(100 * (count($suiteCovered) - count($associated)) / $total, 2);

        return $result;
    }

    /** @return list<int> */
    private function executableLines(string $absolutePath, string $relativePath): array
    {
        return $this->suite['executableLines'][$absolutePath] ?? $this->suite['executableLines'][$relativePath] ?? [];
    }
}
