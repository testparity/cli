<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\Settings;

/**
 * Specs: S006, S007
 *
 * Converts file paths to/from qualified class names.
 * Configurable via Settings for different languages and project structures.
 */
class NamespaceHelper
{
    /** @var array<string, string> directory prefix => namespace prefix */
    protected array $roots;

    protected string $sourceExtension;

    protected string $testSuffix;

    protected string $testExtension;

    protected string $namespaceSeparator;

    public function __construct(?array $roots = null, ?Settings $settings = null)
    {
        if ($settings !== null) {
            $this->roots = $settings->namespaceRoots;
            $this->sourceExtension = $settings->sourceExtension;
            $this->testSuffix = $settings->testSuffix;
            $this->testExtension = $settings->testExtension;
            $this->namespaceSeparator = $settings->namespaceSeparator;
        } else {
            $this->roots = $roots ?? ['app' => 'App', 'tests' => 'Tests'];
            $this->sourceExtension = '.php';
            $this->testSuffix = 'Test';
            $this->testExtension = '.php';
            $this->namespaceSeparator = '\\';
        }
    }

    /**
     * Convert a path relative to project root to a fully qualified class name.
     * e.g. app/Actions/Store.php -> App\Actions\Store
     *      tests/Unit/Actions/StoreTest.php -> Tests\Unit\Actions\StoreTest
     */
    public function pathToFqcn(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        // Strip source extension
        if (str_ends_with($relativePath, $this->sourceExtension)) {
            $relativePath = substr($relativePath, 0, -strlen($this->sourceExtension));
        }

        $roots = $this->roots;
        uksort($roots, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($roots as $dir => $namespace) {
            $dir = trim(str_replace('\\', '/', $dir), '/');
            if ($dir === '') {
                continue;
            }

            $matchesRoot = strtolower($relativePath) === strtolower($dir);
            $matchesChild = str_starts_with(strtolower($relativePath), strtolower($dir).'/');
            if ($matchesRoot || $matchesChild) {
                $rest = $matchesRoot ? '' : substr($relativePath, strlen($dir) + 1);
                $rest = str_replace('/', $this->namespaceSeparator, $rest);

                return $rest !== '' ? $namespace.$this->namespaceSeparator.$rest : $namespace;
            }
        }

        // Default: capitalize first segment
        $segments = explode('/', $relativePath);
        $first = $segments[0] ?? '';
        $namespace = $first === 'app' ? 'App' : ($first === 'tests' ? 'Tests' : ucfirst($first));
        $rest = array_slice($segments, 1);

        return $namespace.$this->namespaceSeparator.implode($this->namespaceSeparator, $rest);
    }

    /**
     * Convert a source file path to the expected test file path.
     * e.g. app/Actions/User.php -> tests/Unit/Actions/UserTest.php
     */
    public function sourcePathToTestPath(
        string $sourceRelativePath,
        string $sourcePathBase,
        string $testPathBase
    ): string {
        $sourcePathBase = rtrim(str_replace('\\', '/', $sourcePathBase), '/');
        $testPathBase = rtrim(str_replace('\\', '/', $testPathBase), '/');

        $path = str_replace('\\', '/', $sourceRelativePath);
        $path = ltrim($path, '/');

        if (! str_starts_with($path, $sourcePathBase.'/') && $path !== $sourcePathBase) {
            return $testPathBase.'/'.basename($path, $this->sourceExtension).$this->testSuffix.$this->testExtension;
        }

        $suffix = substr($path, strlen($sourcePathBase) + 1);
        $baseName = basename($suffix, $this->sourceExtension);
        $subDir = dirname($suffix);
        $middle = $subDir !== '.' ? $subDir.'/' : '';

        return $testPathBase.'/'.$middle.$baseName.$this->testSuffix.$this->testExtension;
    }

    /**
     * Normalize path for comparison (no double slashes, consistent dir separators).
     */
    public function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);

        return trim($path, '/');
    }

    public function getSourceExtension(): string
    {
        return $this->sourceExtension;
    }
}
