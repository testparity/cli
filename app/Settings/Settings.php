<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * Specs: S006, S007
 *
 * Resolved project settings from parity.yaml.
 * Framework and language agnostic — all behavior is driven by config.
 */
class Settings
{
    public function __construct(
        /** Namespace roots: directory prefix → namespace prefix (e.g. 'app' => 'App') */
        public readonly array $namespaceRoots,

        /** File extension for source files (e.g. '.php', '.ts', '.py') */
        public readonly string $sourceExtension,

        /** Test file suffix appended to source basename (e.g. 'Test', '.test', '_test', 'Spec') */
        public readonly string $testSuffix,

        /** Test file extension (defaults to sourceExtension if not set) */
        public readonly string $testExtension,

        /** Separator between class name parts in identifiers (e.g. '\\' for PHP, '.' for Java/Python) */
        public readonly string $namespaceSeparator,

        /** Default per-file minimum coverage */
        public readonly float $minCoverage,

        /** Global minimum coverage (null = not enforced) */
        public readonly ?float $minCoverageGlobal,

        /** Default minimum matched coverage (null = not enforced) */
        public readonly ?float $minMatchedCoverage,

        /** Coverage file candidates (first found is used) */
        public readonly array $coveragePaths,
    ) {}

    /**
     * Build Settings from parsed parity.yaml config, with sensible defaults.
     */
    public static function fromConfig(array $config): self
    {
        $settingsBlock = $config['settings'] ?? [];

        return new self(
            namespaceRoots: $settingsBlock['namespace_roots'] ?? ['app' => 'App', 'tests' => 'Tests'],
            sourceExtension: $settingsBlock['source_extension'] ?? '.php',
            testSuffix: $settingsBlock['test_suffix'] ?? 'Test',
            testExtension: $settingsBlock['test_extension'] ?? ($settingsBlock['source_extension'] ?? '.php'),
            namespaceSeparator: $settingsBlock['namespace_separator'] ?? '\\',
            minCoverage: (float) ($config['min_coverage'] ?? 80),
            minCoverageGlobal: isset($config['min_coverage_global']) ? (float) $config['min_coverage_global'] : null,
            minMatchedCoverage: isset($config['min_matched_coverage']) ? (float) $config['min_matched_coverage'] : null,
            coveragePaths: self::resolveCoveragePaths($config),
        );
    }

    private static function resolveCoveragePaths(array $config): array
    {
        $coverageXml = $config['coverage_xml'] ?? ['coverage-xml', 'clover.xml', 'cobertura.xml'];

        return is_array($coverageXml) ? $coverageXml : [$coverageXml];
    }
}
