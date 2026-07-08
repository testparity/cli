<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\NamespaceHelper;
use App\Services\ParityTestArtifactNormalizer;
use App\Settings\Settings;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Specs: S001, S003, S006
 */
class TestCommand extends Command
{
    protected $signature = 'test
        {--config= : Path to parity.yaml (default: ./parity.yaml)}
        {--format=table : Output format passed to parity check: table (default) or json}
        {--output= : Directory for parity per-test reports (default: test.reports or .parity/per-test)}
        {--show-tests : Forward --show-tests to parity check}
        {--no-check : Only generate per-test reports; do not run parity check afterwards}';

    protected $description = 'Run expected tests individually, write parity per-test coverage reports, and run parity check';

    public function handle(): int
    {
        $configOption = $this->option('config');
        $projectRoot = $configOption
            ? $this->resolveProjectRootFromConfig((string) $configOption)
            : $this->resolveProjectRoot();

        if ($projectRoot === null) {
            $this->error('parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.');

            return self::FAILURE;
        }

        $configPath = $configOption
            ? (realpath((string) $configOption) ?: (string) $configOption)
            : $projectRoot.'/parity.yaml';
        $config = $this->loadConfig($configPath);
        if ($config === null) {
            return self::FAILURE;
        }

        $testConfig = is_array($config['test'] ?? null) ? $config['test'] : [];
        $commandTemplate = isset($testConfig['command']) && is_string($testConfig['command']) ? $testConfig['command'] : null;
        $coverageTemplate = isset($testConfig['coverage']) && is_string($testConfig['coverage']) ? $testConfig['coverage'] : null;
        $reportsRelative = (string) ($this->option('output') ?: ($testConfig['reports'] ?? '.parity/per-test'));

        if ($commandTemplate === null || trim($commandTemplate) === '') {
            $this->error('Missing test.command in parity.yaml. Example: test.command: "./vendor/bin/pest {test} --coverage-clover={coverage}"');

            return self::FAILURE;
        }

        if ($coverageTemplate === null || trim($coverageTemplate) === '') {
            $this->error('Missing test.coverage in parity.yaml. Example: test.coverage: ".parity/tmp/{slug}.xml"');

            return self::FAILURE;
        }

        $settings = Settings::fromConfig($config);
        $namespaceHelper = new NamespaceHelper(settings: $settings);
        $tests = $this->discoverExpectedTests($config, $settings, $namespaceHelper, $projectRoot);

        if ($tests === []) {
            $this->warn('No expected tests discovered from the configured structures.');

            return self::SUCCESS;
        }

        $reportsDir = $this->resolveConfiguredPath($reportsRelative, $projectRoot);
        $reportsSubdir = $reportsDir.'/reports';
        File::deleteDirectory($reportsDir);
        File::ensureDirectoryExists($reportsSubdir);

        $normalizer = new ParityTestArtifactNormalizer;
        $manifest = [
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [],
        ];

        foreach ($tests as $relativeTest => $testIdentifier) {
            $slug = substr(sha1($relativeTest), 0, 16);
            $placeholders = [
                'slug' => $slug,
                'test' => $relativeTest,
                'test_abs' => $projectRoot.'/'.$relativeTest,
                'coverage' => $this->resolveConfiguredPath($this->expandTemplate($coverageTemplate, [
                    'slug' => $slug,
                    'test' => $relativeTest,
                    'test_abs' => $projectRoot.'/'.$relativeTest,
                    'project_root' => $projectRoot,
                ]), $projectRoot),
                'project_root' => $projectRoot,
            ];

            $this->removeCoverageArtifact($placeholders['coverage']);
            $this->ensureCoverageParentExists($placeholders['coverage']);

            $process = Process::fromShellCommandline($this->expandShellCommand($commandTemplate, $placeholders), $projectRoot);
            $process->setTimeout(null);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error("Failed running test [{$relativeTest}]");
                $stderr = trim($process->getErrorOutput());
                $stdout = trim($process->getOutput());
                if ($stderr !== '') {
                    $this->line($stderr);
                }
                if ($stdout !== '') {
                    $this->line($stdout);
                }

                return self::FAILURE;
            }

            $report = $normalizer->normalize($placeholders['coverage'], $testIdentifier, $projectRoot);
            $reportRelPath = 'reports/'.$slug.'.json';
            file_put_contents($reportsDir.'/'.$reportRelPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $manifest['reports'][] = [
                'test' => $testIdentifier,
                'path' => $reportRelPath,
            ];
        }

        file_put_contents($reportsDir.'/index.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        if ((bool) $this->option('no-check')) {
            $this->info("Wrote parity per-test coverage reports to {$reportsRelative}");

            return self::SUCCESS;
        }

        $checkConfig = $config;
        $existingCoverage = $checkConfig['coverage_xml'] ?? [];
        $coverageList = is_array($existingCoverage) ? $existingCoverage : [$existingCoverage];
        array_unshift($coverageList, $reportsRelative);
        $checkConfig['coverage_xml'] = array_values(array_unique(array_map('strval', $coverageList)));

        $tempConfigPath = $projectRoot.'/parity.test.yaml';
        file_put_contents($tempConfigPath, Yaml::dump($checkConfig, 8, 2));

        $arguments = [
            '--config' => $tempConfigPath,
            '--format' => (string) $this->option('format'),
        ];
        if ((bool) $this->option('show-tests')) {
            $arguments['--show-tests'] = true;
        }

        $exitCode = $this->call('check', $arguments);
        @unlink($tempConfigPath);

        return $exitCode;
    }

    /** @return array<string, string> expected test relative path => test identifier */
    private function discoverExpectedTests(array $config, Settings $settings, NamespaceHelper $namespaceHelper, string $projectRoot): array
    {
        $structures = is_array($config['structure'] ?? null) ? $config['structure'] : [];
        $tests = [];

        foreach ($structures as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $sourcePath = $this->resolvePath($entry, 'source');
            $testPath = $this->resolvePath($entry, 'test');
            $fileMap = isset($entry['file_map']) && is_array($entry['file_map']) ? $entry['file_map'] : [];
            $sourceDir = $projectRoot.'/'.trim($sourcePath, '/');
            if (! is_dir($sourceDir)) {
                continue;
            }

            $sourceFiles = File::allFiles($sourceDir);
            foreach ($sourceFiles as $file) {
                if (! str_ends_with($file->getFilename(), $settings->sourceExtension)) {
                    continue;
                }

                $relativeSource = $namespaceHelper->normalizeRelativePath($file->getRelativePathname());
                $fullSourceRelative = trim($sourcePath, '/').'/'.$relativeSource;
                $mappedTest = $fileMap[$relativeSource] ?? null;
                $expectedTestRelative = $mappedTest !== null
                    ? trim($testPath, '/').'/'.$mappedTest
                    : $namespaceHelper->sourcePathToTestPath($fullSourceRelative, trim($sourcePath, '/'), trim($testPath, '/'));
                $testAbsolute = $projectRoot.'/'.$expectedTestRelative;
                if (! is_file($testAbsolute)) {
                    continue;
                }

                $tests[$expectedTestRelative] = $namespaceHelper->pathToFqcn($expectedTestRelative);
            }
        }

        ksort($tests);

        return $tests;
    }

    private function expandShellCommand(string $template, array $placeholders): string
    {
        $replace = [];
        foreach ($placeholders as $key => $value) {
            $replace['{'.$key.'}'] = escapeshellarg((string) $value);
        }

        return strtr($template, $replace);
    }

    private function expandTemplate(string $template, array $placeholders): string
    {
        $replace = [];
        foreach ($placeholders as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    private function ensureCoverageParentExists(string $path): void
    {
        if ($this->looksLikeDirectoryTarget($path)) {
            File::ensureDirectoryExists($path);

            return;
        }

        $parent = dirname($path);
        if ($parent !== '' && $parent !== '.') {
            File::ensureDirectoryExists($parent);
        }
    }

    private function removeCoverageArtifact(string $path): void
    {
        if (is_dir($path)) {
            File::deleteDirectory($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    private function looksLikeDirectoryTarget(string $path): bool
    {
        $basename = basename($path);

        return ! str_contains($basename, '.');
    }

    private function resolveConfiguredPath(string $path, string $projectRoot): string
    {
        if ($path === '') {
            return $projectRoot;
        }

        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $projectRoot.'/'.ltrim($path, '/');
    }

    private function resolvePath(array $entry, string $key): string
    {
        if (isset($entry['paths']) && is_array($entry['paths'])) {
            return (string) ($entry['paths'][$key] ?? '');
        }

        return (string) ($entry["{$key}_path"] ?? '');
    }

    private function resolveProjectRoot(): ?string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return null;
        }
        if (! is_file($cwd.'/parity.yaml')) {
            return null;
        }

        return realpath($cwd) ?: $cwd;
    }

    private function resolveProjectRootFromConfig(string $configPath): ?string
    {
        $resolved = realpath($configPath);
        if ($resolved === false || ! is_file($resolved)) {
            return null;
        }

        return dirname($resolved);
    }

    private function loadConfig(string $configPath): ?array
    {
        if (! is_file($configPath)) {
            return null;
        }

        $contents = @file_get_contents($configPath);
        if ($contents === false) {
            $this->error("Could not read config: {$configPath}");

            return null;
        }

        try {
            $config = Yaml::parse($contents);
        } catch (ParseException $e) {
            $this->error("Invalid YAML in {$configPath}: {$e->getMessage()}");

            return null;
        }

        return is_array($config) ? $config : null;
    }
}
