<?php

declare(strict_types=1);

namespace App\Services;

final class GitHubCoverageReport
{
    public function render(array $report, bool $compareSuite = false): string
    {
        $lines = ['## TestParity coverage', '', $report['passed'] ? '**Passed**' : '**Failed**'];

        if ($report['global_coverage'] !== null) {
            $coverage = ($compareSuite ? 'Rule input global coverage: ' : 'Reported global coverage: ').$report['global_coverage'].'%';
            if ($report['min_coverage_global'] !== null) {
                $coverage .= ' (minimum '.$report['min_coverage_global'].'%)';
            }
            array_push($lines, '', $coverage);
        }

        if ($compareSuite) {
            array_push($lines, '', 'The gap is the share of executable lines covered by the full suite but missed by the associated test. A dash indicates unavailable or incompatible line evidence. Check results use the configured coverage input and rules.');
        }

        foreach ($report['structures'] as $structure) {
            array_push(
                $lines,
                '',
                '### '.$this->code($structure['name']),
                '',
                $compareSuite
                    ? '| Source | Associated test | Full suite | Associated test only | Gap | Check result |'
                    : '| Source | Associated test | Reported coverage | Associated coverage | Result |',
                $compareSuite ? '| --- | --- | ---: | ---: | ---: | --- |' : '| --- | --- | ---: | ---: | --- |',
            );

            foreach ($structure['files'] as $file) {
                $failedRules = array_keys(array_filter($file['rules'], static fn (array $rule): bool => ! $rule['passed']));
                $result = $file['passed'] ? 'Passed' : 'Failed';
                if ($failedRules !== []) {
                    $result .= ($file['passed'] ? ' with warnings · ' : ' · ')
                        .implode(', ', array_map($this->code(...), $failedRules));
                }
                $matched = $file['rules']['matched-coverage']['value'] ?? '—';
                $coverage = $compareSuite
                    ? $this->formatCoverage($file['comparison']['suite']).' | '
                        .$this->formatCoverage($file['comparison']['associated']).' | '
                        .$this->formatCoverage($file['comparison']['gap'], ' pp')
                    : $file['coverage'].'% | '.$this->code($matched);
                $lines[] = '| '.$this->code($file['source']).' | '.$this->code($file['test'])
                    .' | '.$coverage.' | '.$result.' |';
            }
        }

        return implode("\n", $lines);
    }

    private function formatCoverage(?float $value, string $unit = '%'): string
    {
        return $value === null ? '—' : $value.$unit;
    }

    private function code(string $value): string
    {
        $escaped = htmlspecialchars(str_replace(["\r", "\n"], ' ', $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<code>'.strtr($escaped, [
            '!' => '&#33;',
            '*' => '&#42;',
            '@' => '&#64;',
            '[' => '&#91;',
            '\\' => '&#92;',
            ']' => '&#93;',
            '_' => '&#95;',
            '`' => '&#96;',
            '|' => '&#124;',
            '~' => '&#126;',
        ]).'</code>';
    }
}
