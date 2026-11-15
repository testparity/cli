<?php

declare(strict_types=1);

namespace App\Services;

final class GitHubCoverageReport
{
    public function render(array $report): string
    {
        $lines = ['## TestParity coverage', '', $report['passed'] ? '**Passed**' : '**Failed**'];

        if ($report['global_coverage'] !== null) {
            $coverage = 'Reported global coverage: '.$report['global_coverage'].'%';
            if ($report['min_coverage_global'] !== null) {
                $coverage .= ' (minimum '.$report['min_coverage_global'].'%)';
            }
            array_push($lines, '', $coverage);
        }

        foreach ($report['structures'] as $structure) {
            array_push(
                $lines,
                '',
                '### '.$this->code($structure['name']),
                '',
                '| Source | Associated test | Reported coverage | Associated coverage | Result |',
                '| --- | --- | ---: | ---: | --- |',
            );

            foreach ($structure['files'] as $file) {
                $failedRules = array_keys(array_filter($file['rules'], static fn (array $rule): bool => ! $rule['passed']));
                $result = $file['passed'] ? 'Passed' : 'Failed';
                if ($failedRules !== []) {
                    $result .= ($file['passed'] ? ' with warnings · ' : ' · ')
                        .implode(', ', array_map($this->code(...), $failedRules));
                }
                $matched = $file['rules']['matched-coverage']['value'] ?? '—';
                $lines[] = '| '.$this->code($file['source']).' | '.$this->code($file['test'])
                    .' | '.$file['coverage'].'% | '.$this->code($matched).' | '.$result.' |';
            }
        }

        return implode("\n", $lines);
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
