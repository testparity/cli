<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\GitChangedLines;
use App\Services\NamespaceHelper;
use App\Services\ParityPerTestCoverageReader;
use App\Settings\Settings;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Specs: S003, S011
 */
final class PrCommand extends Command
{
    protected $signature = 'pr
        {--base= : Git ref to compare against}
        {--format=table : Output format: table or json}
        {--config=parity.yaml : Path to the project configuration}
        {--reports= : Override the per-test report directory}';

    protected $description = 'Report associated-test coverage on changed executable lines';

    public function handle(GitChangedLines $git, ParityPerTestCoverageReader $reader): int
    {
        $format = $this->option('format');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error('Choose --format=table or --format=json.');

            return self::INVALID;
        }

        $base = (string) $this->option('base');
        try {
            $configPath = realpath((string) $this->option('config'));
            if ($configPath === false || ! is_file($configPath)) {
                throw new RuntimeException('parity.yaml not found. Run from the project root or use --config.');
            }

            $config = Yaml::parseFile($configPath);
            if (! is_array($config)) {
                throw new RuntimeException('Expected a mapping in the Parity configuration.');
            }

            $root = dirname($configPath);
            $changes = $git->read($root, $base);
            $this->validatedThreshold($config['min_matched_coverage'] ?? null);
            $settings = Settings::fromConfig($config);
            $namespace = new NamespaceHelper(settings: $settings);
            $reports = (string) ($this->option('reports') ?: ($config['test']['reports'] ?? '.parity/per-test'));
            $coverage = $reader->read(Path::isAbsolute($reports) ? $reports : $root.'/'.$reports, $root);
            $rows = [];
            $structures = $config['structure'] ?? [];
            if (! is_array($structures)) {
                throw new RuntimeException('Expected a list of Parity structures.');
            }

            foreach ($structures as $structure) {
                if (! is_array($structure) || (isset($structure['rules']) && ! is_array($structure['rules']))) {
                    throw new RuntimeException('Expected a mapping for each structure and a list for its rules.');
                }
                $source = trim((string) ($structure['paths']['source'] ?? $structure['source_path'] ?? ''), '/');
                $tests = trim((string) ($structure['paths']['test'] ?? $structure['test_path'] ?? ''), '/');
                if ($source === '' || $tests === '') {
                    continue;
                }

                $threshold = $this->threshold($structure, $settings);
                foreach ($changes['files'] as $change) {
                    if (! str_starts_with($change['path'], $source.'/') || ! str_ends_with($change['path'], $settings->sourceExtension)) {
                        continue;
                    }

                    $relative = substr($change['path'], strlen($source) + 1);
                    $mapped = $structure['file_map'][$relative] ?? null;
                    $test = $mapped !== null
                        ? $tests.'/'.$mapped
                        : $namespace->sourcePathToTestPath($change['path'], $source, $tests);
                    $identifier = $namespace->pathToFqcn($test);
                    $rows[] = $this->fileReport($change, $coverage, $test, $identifier, is_file($root.'/'.$test), $threshold, $structure['name'] ?? 'Unnamed');
                }
            }
        } catch (RuntimeException $e) {
            if ($format === 'json') {
                $this->line(json_encode(['version' => 1, 'status' => 'error', 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE));
            } else {
                $this->error($e->getMessage());
            }

            return self::INVALID;
        }

        $statuses = array_column($rows, 'status');
        $unavailable = in_array('attribution_unavailable', $statuses, true);
        $failed = in_array('failed', $statuses, true) || in_array('missing_test', $statuses, true);
        $status = $unavailable ? 'attribution_unavailable' : ($failed ? 'failed' : 'passed');
        if ($format === 'json') {
            $this->line(json_encode([
                'version' => 1,
                'base' => $base,
                'merge_base' => $changes['merge_base'],
                'status' => $status,
                'files' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } else {
            $this->info("PR coverage against {$base} ({$changes['merge_base']})");
            $this->table(
                ['Source', 'Associated test', 'Associated lines', 'Unrelated only', 'Uncovered in reports', 'Matched', 'Minimum', 'Status'],
                array_map(fn (array $row): array => [
                    $row['path'],
                    $row['test_exists'] ? $row['associated_test'] : $row['associated_test'].' (missing)',
                    implode(', ', $row['associated_lines']),
                    implode(', ', $row['unrelated_lines']),
                    implode(', ', $row['uncovered_lines']),
                    $row['matched_percent'] === null ? '-' : $row['matched_percent'].'%',
                    $row['threshold'] === null ? '-' : $row['threshold'].'%',
                    $row['status'],
                ], $rows)
            );
            if ($unavailable) {
                $this->warn('Generate fresh per-test reports with parity test --no-check and verify the coverage runner includes the changed source.');
            }
        }

        return $unavailable ? self::INVALID : ($failed ? self::FAILURE : self::SUCCESS);
    }

    private function fileReport(array $change, array $coverage, string $test, string $identifier, bool $exists, ?float $threshold, string $structure): array
    {
        $path = $change['path'];
        $row = [
            'path' => $path,
            'previous_path' => $change['previous_path'],
            'git_status' => $change['status'],
            'structure' => $structure,
            'associated_test' => $test,
            'test_exists' => $exists,
            'changed_lines' => $change['lines'],
            'associated_lines' => [],
            'unrelated_lines' => [],
            'uncovered_lines' => [],
            'matched_percent' => null,
            'threshold' => $threshold,
            'passed' => null,
            'status' => 'not_applicable',
        ];

        if ($change['lines'] === []) {
            return $row;
        }

        if (! ($coverage['complete'] ?? false) || ! isset($coverage['executableLines'][$path])
            || ($exists && ! in_array($identifier, $coverage['reportedTests'], true))) {
            $row['status'] = 'attribution_unavailable';

            return $row;
        }

        $executable = array_intersect($change['lines'], $coverage['executableLines'][$path]);
        foreach ($executable as $line) {
            $owners = $coverage['lineCoverage'][$path][$line] ?? [];
            if ($exists && in_array($identifier, $owners, true)) {
                $row['associated_lines'][] = $line;
            } elseif ($owners !== []) {
                $row['unrelated_lines'][] = $line;
            } else {
                $row['uncovered_lines'][] = $line;
            }
        }

        if ($executable === []) {
            return $row;
        }

        $percent = round(100 * count($row['associated_lines']) / count($executable), 2);
        $row['matched_percent'] = $percent;
        $row['passed'] = $threshold === null ? null : $exists && $percent >= $threshold;
        $row['status'] = ! $exists ? 'missing_test' : ($row['passed'] === false ? 'failed' : ($threshold === null ? 'reported' : 'passed'));

        return $row;
    }

    private function threshold(array $structure, Settings $settings): ?float
    {
        foreach ($structure['rules'] ?? [] as $rule) {
            if ($rule === 'matched-coverage') {
                return null;
            }
            if (is_array($rule) && ($rule['name'] ?? null) === 'matched-coverage') {
                return $this->validatedThreshold($rule['min'] ?? null);
            }
            if (is_array($rule) && array_key_exists('matched-coverage', $rule)) {
                return $this->validatedThreshold($rule['matched-coverage']['min'] ?? null);
            }
        }

        if (isset($structure['rules'])) {
            return null;
        }

        return $this->validatedThreshold($structure['min_matched_coverage'] ?? $settings->minMatchedCoverage);
    }

    private function validatedThreshold(mixed $threshold): ?float
    {
        if ($threshold === null) {
            return null;
        }
        if (! is_numeric($threshold) || $threshold < 0 || $threshold > 100) {
            throw new RuntimeException('The matched-coverage minimum must be a number between 0 and 100.');
        }

        return (float) $threshold;
    }
}
