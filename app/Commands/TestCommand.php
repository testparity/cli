<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\NamespaceHelper;
use App\Services\ParityTestArtifactNormalizer;
use App\Services\ParityTestWorkspace;
use App\Settings\Settings;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Specs: S001, S003, S006, S011
 */
class TestCommand extends Command
{
    protected $signature = 'test
        {--config= : Path to parity.yaml (default: ./parity.yaml)}
        {--format=table : Output format passed to parity check: table (default) or json}
        {--output= : Directory for parity per-test reports (default: test.reports or .parity/per-test)}
        {--timeout= : Maximum seconds for each test process (default: test.timeout or 300)}
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
        $timeout = $this->resolveTimeout($testConfig);

        if ($commandTemplate === null || trim($commandTemplate) === '') {
            $this->error('Missing test.command in parity.yaml. Example: test.command: "./vendor/bin/pest {test} --coverage-clover={coverage}"');

            return self::FAILURE;
        }

        if ($coverageTemplate === null || trim($coverageTemplate) === '') {
            $this->error('Missing test.coverage in parity.yaml. Example: test.coverage: ".parity/tmp/{slug}.xml"');

            return self::FAILURE;
        }

        if ($timeout === null) {
            return self::FAILURE;
        }

        $settings = Settings::fromConfig($config);
        $namespaceHelper = new NamespaceHelper(settings: $settings);
        $tests = $this->discoverExpectedTests($config, $settings, $namespaceHelper, $projectRoot);

        if ($tests === []) {
            $this->warn('No expected tests discovered from the configured structures.');

            return self::SUCCESS;
        }

        $workspace = new ParityTestWorkspace($projectRoot);
        try {
            $reportsDir = $workspace->reportTarget($this->resolveConfiguredPath($reportsRelative, $projectRoot));
            $stagingDir = $workspace->createStagingDirectory($reportsDir);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $normalizer = new ParityTestArtifactNormalizer;
        $manifest = [
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [],
        ];

        try {
            foreach ($tests as $relativeTest => $testIdentifier) {
                $slug = substr(sha1($relativeTest), 0, 16);
                $coverageConfiguredPath = $this->resolveConfiguredPath($this->expandTemplate($coverageTemplate, [
                    'slug' => $slug,
                    'test' => $relativeTest,
                    'test_abs' => $projectRoot.'/'.$relativeTest,
                    'project_root' => $projectRoot,
                ]), $projectRoot);

                try {
                    $coveragePath = $workspace->prepareCoverageArtifact($coverageConfiguredPath, $reportsDir);
                } catch (RuntimeException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }

                try {
                    $placeholders = [
                        'slug' => $slug,
                        'test' => $relativeTest,
                        'test_abs' => $projectRoot.'/'.$relativeTest,
                        'coverage' => $coveragePath,
                        'project_root' => $projectRoot,
                    ];

                    $process = Process::fromShellCommandline($this->expandShellCommand($commandTemplate, $placeholders), $projectRoot);
                    $process->setTimeout($timeout);

                    try {
                        $process->run();
                    } catch (ProcessTimedOutException) {
                        $this->error("Timed out running test [{$relativeTest}] after {$timeout} seconds.");

                        return self::FAILURE;
                    }

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

                    $report = $normalizer->normalize($coveragePath, $testIdentifier, $projectRoot);
                    if (! $workspace->reportHasExecutableCoverage($report)) {
                        $this->error("Coverage artifact for test [{$relativeTest}] did not contain executable source coverage.");

                        return self::FAILURE;
                    }

                    $reportRelPath = 'reports/'.$slug.'.json';
                    try {
                        $workspace->writeJsonFile($stagingDir.'/'.$reportRelPath, $report);
                    } catch (RuntimeException $e) {
                        $this->error($e->getMessage());

                        return self::FAILURE;
                    }

                    $manifest['reports'][] = [
                        'test' => $testIdentifier,
                        'path' => $reportRelPath,
                    ];
                } finally {
                    $workspace->removePreparedCoverageArtifact($coveragePath);
                }
            }

            try {
                $workspace->writeJsonFile($stagingDir.'/index.json', $manifest);
                $warning = $workspace->publishReports($stagingDir, $reportsDir);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            if ($warning !== null) {
                $this->warn($warning);
            }

            if ((bool) $this->option('no-check')) {
                $this->info("Wrote parity per-test coverage reports to {$reportsRelative}");

                return self::SUCCESS;
            }

            return $this->runCheck($config, $reportsRelative, $projectRoot);
        } finally {
            $workspace->cleanupDirectory($stagingDir);
        }
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
            if (trim($sourcePath) === '' || trim($testPath) === '') {
                continue;
            }
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

    private function resolveTimeout(array $testConfig): ?float
    {
        $configured = $this->option('timeout');
        if ($configured === null || $configured === '') {
            $configured = $testConfig['timeout'] ?? 300;
        }

        if (! is_numeric($configured) || (float) $configured <= 0) {
            $this->error('test.timeout and --timeout must be a positive number of seconds.');

            return null;
        }

        return (float) $configured;
    }

    private function runCheck(array $config, string $reportsPath, string $projectRoot): int
    {
        $checkConfig = $config;
        $existingCoverage = $checkConfig['coverage_xml'] ?? [];
        $coverageList = is_array($existingCoverage) ? $existingCoverage : [$existingCoverage];
        array_unshift($coverageList, $reportsPath);
        $checkConfig['coverage_xml'] = array_values(array_unique(array_map('strval', $coverageList)));

        $tempConfigPath = @tempnam($projectRoot, '.parity-test-');
        if ($tempConfigPath === false) {
            $this->error('Could not create a temporary config for parity check.');

            return self::FAILURE;
        }

        try {
            $contents = Yaml::dump($checkConfig, 8, 2);
            if (file_put_contents($tempConfigPath, $contents) === false) {
                $this->error('Could not write the temporary config for parity check.');

                return self::FAILURE;
            }

            $arguments = [
                '--config' => $tempConfigPath,
                '--format' => (string) $this->option('format'),
            ];
            if ((bool) $this->option('show-tests')) {
                $arguments['--show-tests'] = true;
            }

            return $this->call('check', $arguments);
        } finally {
            if (is_file($tempConfigPath)) {
                @unlink($tempConfigPath);
            }
        }
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
