<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Converts file paths to/from PSR-4 class names for Laravel app/ and tests/.
 */
class NamespaceHelper
{
    /**
     * PSR-4 root directory to namespace prefix (without trailing backslash).
     *
     * @var array<string, string>
     */
    protected array $roots = [
        'app' => 'App',
        'tests' => 'Tests',
    ];

    public function __construct(?array $roots = null)
    {
        if ($roots !== null) {
            $this->roots = $roots;
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

        // Strip .php
        if (str_ends_with($relativePath, '.php')) {
            $relativePath = substr($relativePath, 0, -4);
        }

        $segments = explode('/', $relativePath);
        $first = $segments[0] ?? '';

        foreach ($this->roots as $dir => $namespace) {
            if (strtolower($first) === strtolower($dir)) {
                $rest = array_slice($segments, 1);
                $class = implode('\\', array_map(
                    fn (string $s) => $s,
                    $rest
                ));

                return $namespace . '\\' . $class;
            }
        }

        // Default Laravel: app -> App, tests -> Tests
        $namespace = $first === 'app' ? 'App' : ($first === 'tests' ? 'Tests' : ucfirst($first));
        $rest = array_slice($segments, 1);

        return $namespace . '\\' . implode('\\', $rest);
    }

    /**
     * Convert a source file path to the expected test file path.
     * e.g. app/Actions/User.php -> tests/Unit/Actions/UserTest.php
     * Uses the configured test_path base (e.g. tests/Unit/Actions).
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

        if (! str_starts_with($path, $sourcePathBase . '/') && $path !== $sourcePathBase) {
            return $testPathBase . '/' . basename($path, '.php') . 'Test.php';
        }

        $suffix = substr($path, strlen($sourcePathBase) + 1);
        $baseName = basename($suffix, '.php');
        $subDir = dirname($suffix);
        $middle = $subDir !== '.' ? $subDir . '/' : '';

        return $testPathBase . '/' . $middle . $baseName . 'Test.php';
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
}
