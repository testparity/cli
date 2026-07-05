<?php

declare(strict_types=1);

// Specs: S006-FR-001, S006-FR-002, S007-FR-001, S010-FR-005

namespace Tests\Unit\Settings;

use App\Settings\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    // --- Defaults ---

    public function test_from_config_uses_sensible_defaults_for_empty_config(): void
    {
        $settings = Settings::fromConfig([]);

        expect($settings->namespaceRoots)->toBe(['app' => 'App', 'tests' => 'Tests']);
        expect($settings->sourceExtension)->toBe('.php');
        expect($settings->testSuffix)->toBe('Test');
        expect($settings->testExtension)->toBe('.php');
        expect($settings->namespaceSeparator)->toBe('\\');
        expect($settings->minCoverage)->toBe(80.0);
        expect($settings->minCoverageGlobal)->toBeNull();
        expect($settings->minMatchedCoverage)->toBeNull();
        expect($settings->coveragePaths)->toBe(['coverage-xml', 'clover.xml', 'cobertura.xml']);
    }

    // --- settings block ---

    public function test_from_config_reads_namespace_roots(): void
    {
        $settings = Settings::fromConfig([
            'settings' => [
                'namespace_roots' => ['src' => 'MyApp', 'tests' => 'MyApp\\Tests'],
            ],
        ]);

        expect($settings->namespaceRoots)->toBe(['src' => 'MyApp', 'tests' => 'MyApp\\Tests']);
    }

    public function test_from_config_reads_source_extension(): void
    {
        $settings = Settings::fromConfig([
            'settings' => ['source_extension' => '.ts'],
        ]);

        expect($settings->sourceExtension)->toBe('.ts');
    }

    public function test_from_config_test_extension_defaults_to_source_extension(): void
    {
        $settings = Settings::fromConfig([
            'settings' => ['source_extension' => '.ts'],
        ]);

        expect($settings->testExtension)->toBe('.ts');
    }

    public function test_from_config_reads_explicit_test_extension(): void
    {
        $settings = Settings::fromConfig([
            'settings' => [
                'source_extension' => '.ts',
                'test_extension' => '.spec.ts',
            ],
        ]);

        expect($settings->testExtension)->toBe('.spec.ts');
    }

    public function test_from_config_reads_test_suffix(): void
    {
        $settings = Settings::fromConfig([
            'settings' => ['test_suffix' => 'Spec'],
        ]);

        expect($settings->testSuffix)->toBe('Spec');
    }

    public function test_from_config_reads_namespace_separator(): void
    {
        $settings = Settings::fromConfig([
            'settings' => ['namespace_separator' => '.'],
        ]);

        expect($settings->namespaceSeparator)->toBe('.');
    }

    // --- top-level coverage keys ---

    public function test_from_config_reads_min_coverage(): void
    {
        $settings = Settings::fromConfig(['min_coverage' => 70]);

        expect($settings->minCoverage)->toBe(70.0);
    }

    public function test_from_config_reads_min_coverage_global(): void
    {
        $settings = Settings::fromConfig(['min_coverage_global' => 85]);

        expect($settings->minCoverageGlobal)->toBe(85.0);
    }

    public function test_from_config_min_coverage_global_is_null_when_not_set(): void
    {
        $settings = Settings::fromConfig([]);

        expect($settings->minCoverageGlobal)->toBeNull();
    }

    public function test_from_config_reads_min_matched_coverage(): void
    {
        $settings = Settings::fromConfig(['min_matched_coverage' => 60]);

        expect($settings->minMatchedCoverage)->toBe(60.0);
    }

    public function test_from_config_min_matched_coverage_is_null_when_not_set(): void
    {
        $settings = Settings::fromConfig([]);

        expect($settings->minMatchedCoverage)->toBeNull();
    }

    // --- coverage_xml path resolution ---

    public function test_from_config_reads_coverage_paths_as_array(): void
    {
        $settings = Settings::fromConfig([
            'coverage_xml' => ['build/clover.xml', 'coverage/clover.xml'],
        ]);

        expect($settings->coveragePaths)->toBe(['build/clover.xml', 'coverage/clover.xml']);
    }

    public function test_from_config_wraps_single_string_coverage_path_in_array(): void
    {
        $settings = Settings::fromConfig([
            'coverage_xml' => 'build/clover.xml',
        ]);

        expect($settings->coveragePaths)->toBe(['build/clover.xml']);
    }

    public function test_from_config_defaults_coverage_paths_when_not_set(): void
    {
        $settings = Settings::fromConfig([]);

        expect($settings->coveragePaths)->toBe(['coverage-xml', 'clover.xml', 'cobertura.xml']);
    }

    // --- full realistic config ---

    public function test_from_config_full_realistic_config(): void
    {
        $config = [
            'settings' => [
                'namespace_roots' => ['app' => 'App', 'tests' => 'Tests'],
                'source_extension' => '.php',
                'test_suffix' => 'Test',
                'test_extension' => '.php',
                'namespace_separator' => '\\',
            ],
            'min_coverage' => 90,
            'min_coverage_global' => 85,
            'min_matched_coverage' => 70,
            'coverage_xml' => ['clover.xml'],
        ];

        $settings = Settings::fromConfig($config);

        expect($settings->namespaceRoots)->toBe(['app' => 'App', 'tests' => 'Tests']);
        expect($settings->sourceExtension)->toBe('.php');
        expect($settings->testSuffix)->toBe('Test');
        expect($settings->testExtension)->toBe('.php');
        expect($settings->namespaceSeparator)->toBe('\\');
        expect($settings->minCoverage)->toBe(90.0);
        expect($settings->minCoverageGlobal)->toBe(85.0);
        expect($settings->minMatchedCoverage)->toBe(70.0);
        expect($settings->coveragePaths)->toBe(['clover.xml']);
    }

    // --- immutability ---

    public function test_settings_properties_are_readonly(): void
    {
        $settings = Settings::fromConfig([]);

        $reflection = new \ReflectionClass($settings);
        foreach ($reflection->getProperties() as $property) {
            expect($property->isReadOnly())->toBeTrue(
                "Property {$property->getName()} should be readonly"
            );
        }
    }
}
