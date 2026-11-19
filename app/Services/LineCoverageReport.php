<?php

declare(strict_types=1);

namespace App\Services;

/** Builds source identity and exact associated-test evidence, never inferred from percentages. */
class LineCoverageReport
{
    public function build(string $source, string $absolute, string $relative, string $test, bool $testExists, array $data): array
    {
        $report = ['version' => 1, 'complete' => false];
        $contents = is_readable($source) ? file_get_contents($source) : false;
        if ($contents === false) {
            return $report + ['reason' => 'Source file is unreadable.'];
        }
        $lines = $contents === '' ? [] : preg_split('/\r\n|\n|\r/', $contents);
        if ($lines !== [] && preg_match('/(?:\r\n|\n|\r)$/', $contents)) {
            array_pop($lines);
        }
        $report['sourceHash'] = hash('sha256', $contents);
        $report['lineHashes'] = array_map(fn (string $line): string => hash('sha256', $line), $lines);
        if (! ($data['attributionComplete'] ?? false)) {
            return $report + ['reason' => 'Complete test-attributed coverage is unavailable.'];
        }
        $executable = $data['executableLines'][$absolute] ?? $data['executableLines'][$relative] ?? null;
        $total = $data['totalExecutable'][$absolute] ?? $data['totalExecutable'][$relative] ?? 0;
        if ($executable === null || count($executable) !== $total || count(array_unique($executable)) !== count($executable)) {
            return $report + ['reason' => 'Complete executable line metadata is unavailable.'];
        }
        foreach ($executable as $line) {
            if (! is_int($line) || $line < 1 || $line > count($lines)) {
                return $report + ['reason' => 'Executable line metadata does not match the source.'];
            }
        }
        sort($executable, SORT_NUMERIC);
        foreach ($data['lineSignaturesByTest'] ?? [] as $files) {
            if (isset($files[$relative]) && $files[$relative] !== SuiteCoverageComparison::lineSignature($executable)) {
                return $report + ['reason' => 'Per-test reports have inconsistent executable lines.'];
            }
        }
        if ($testExists && is_array($data['reportedTests'] ?? null)) {
            $signature = $data['lineSignaturesByTest'][$test][$relative] ?? null;
            if (! in_array($test, $data['reportedTests'], true) || $signature !== SuiteCoverageComparison::lineSignature($executable)) {
                return $report + ['reason' => 'The associated test report is absent or has inconsistent executable lines.'];
            }
        }
        $covered = [];
        $perLine = $data['lineCoverage'][$absolute] ?? $data['lineCoverage'][$relative] ?? [];
        foreach ($perLine as $line => $tests) {
            if (! is_int($line) || ! in_array($line, $executable, true)) {
                return $report + ['reason' => 'Attributed lines are outside executable line metadata.'];
            }
            foreach ($tests as $identifier) {
                $class = explode('::', $identifier, 2)[0];
                if (str_starts_with($class, 'P\\')) {
                    $class = substr($class, 2);
                }
                if ($testExists && $class === $test) {
                    $covered[] = (int) $line;
                    break;
                }
            }
        }
        sort($covered, SORT_NUMERIC);
        $report['complete'] = true;
        $report['executableLines'] = $executable;
        $report['coveredLines'] = $covered;

        return $report;
    }
}
