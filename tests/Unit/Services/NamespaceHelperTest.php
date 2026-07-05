<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\NamespaceHelper;
use App\Settings\Settings;
use PHPUnit\Framework\TestCase;

class NamespaceHelperTest extends TestCase
{
    // Specs: S007-FR-001, S007-FR-002, S007-FR-007, S010-FR-005

    public function test_maps_php_paths_using_default_namespace_settings(): void
    {
        $helper = new NamespaceHelper;

        expect($helper->pathToFqcn('app/Actions/StoreUser.php'))->toBe('App\\Actions\\StoreUser');
        expect($helper->sourcePathToTestPath('app/Actions/StoreUser.php', 'app', 'tests/Unit'))->toBe('tests/Unit/Actions/StoreUserTest.php');
    }

    public function test_maps_typescript_paths_with_configured_separator_and_suffix(): void
    {
        $helper = new NamespaceHelper(settings: Settings::fromConfig([
            'settings' => [
                'namespace_roots' => ['src' => 'Src', 'tests' => 'Tests'],
                'source_extension' => '.ts',
                'test_suffix' => '.test',
                'test_extension' => '.ts',
                'namespace_separator' => '.',
            ],
        ]));

        expect($helper->pathToFqcn('src/utils/formatCurrency.ts'))->toBe('Src.utils.formatCurrency');
        expect($helper->sourcePathToTestPath('src/utils/formatCurrency.ts', 'src', 'tests'))->toBe('tests/utils/formatCurrency.test.ts');
    }

    public function test_maps_nested_namespace_roots_before_single_segment_fallback(): void
    {
        $helper = new NamespaceHelper(settings: Settings::fromConfig([
            'settings' => [
                'namespace_roots' => ['code/src' => 'App', 'code/tests' => 'Tests'],
                'source_extension' => '.php',
                'test_suffix' => 'Test',
                'test_extension' => '.php',
                'namespace_separator' => '\\',
            ],
        ]));

        expect($helper->pathToFqcn('code/src/Slugger.php'))->toBe('App\\Slugger');
        expect($helper->pathToFqcn('code/tests/SluggerTest.php'))->toBe('Tests\\SluggerTest');
    }

    public function test_maps_rust_paths_with_configured_suffix(): void
    {
        $helper = new NamespaceHelper(settings: Settings::fromConfig([
            'settings' => [
                'namespace_roots' => ['src' => 'crate', 'tests' => 'tests'],
                'source_extension' => '.rs',
                'test_suffix' => '_test',
                'test_extension' => '.rs',
                'namespace_separator' => '::',
            ],
        ]));

        expect($helper->pathToFqcn('src/lib.rs'))->toBe('crate::lib');
        expect($helper->sourcePathToTestPath('src/lib.rs', 'src', 'tests'))->toBe('tests/lib_test.rs');
    }

    public function test_normalizes_relative_paths_for_cross_platform_comparison(): void
    {
        $helper = new NamespaceHelper;

        expect($helper->normalizeRelativePath('/app//Actions\\StoreUser.php'))->toBe('app/Actions/StoreUser.php');
    }
}
