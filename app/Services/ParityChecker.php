<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\CoverageLinkers\CoverageLinkerRegistry;

class ParityChecker
{
    public function __construct(
        protected NamespaceHelper $namespaceHelper,
        protected string $projectRoot
    ) {}

    /**
     * Ensure the project's autoload is loaded so Reflection can resolve classes.
     */
    public function ensureAutoloadLoaded(): void
    {
        $autoload = $this->projectRoot . '/vendor/autoload.php';
        if (is_file($autoload) && ! class_exists(\Composer\Autoload\ClassLoader::class, false)) {
            require_once $autoload;
        }
    }

    /**
     * Get the fully qualified class name of the first class defined in a PHP file.
     * Uses path and source parsing only; does not load the file.
     */
    public function getFqcnFromTestFile(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        $relativePath = $this->relativePathFromRoot($absolutePath);
        $fqcn = $this->namespaceHelper->pathToFqcn($relativePath);

        $declared = $this->extractDeclaredClassFromSource($content);

        return $declared ?? $fqcn;
    }

    /**
     * Validate coverage link using the linker registry (supports Pest, PHPUnit attributes, etc.)
     *
     * @return array{valid: bool, error: string|null, linker: string|null}
     */
    public function validateCoverageLink(
        string $testFileAbsolutePath,
        string $expectedSourceFqcn,
        CoverageLinkerRegistry $registry
    ): array {
        if (! is_file($testFileAbsolutePath)) {
            return ['valid' => false, 'error' => 'Test file not found', 'linker' => null];
        }

        $content = @file_get_contents($testFileAbsolutePath);
        if ($content === false) {
            return ['valid' => false, 'error' => 'Could not read test file', 'linker' => null];
        }

        $useMap = $this->extractUseMap($content);
        $namespace = $this->extractClassNamespace($content);

        $result = $registry->extractCoveredClasses($content, $useMap, $namespace);

        if ($result['linker'] === null) {
            return ['valid' => false, 'error' => 'No compatible linker for this test file', 'linker' => null];
        }

        if ($result['classes'] === []) {
            return ['valid' => false, 'error' => 'Missing coverage declaration (no covers/CoversClass found)', 'linker' => $result['linker']];
        }

        if (! in_array($expectedSourceFqcn, $result['classes'], true)) {
            $found = implode(', ', $result['classes']);

            return [
                'valid' => false,
                'error' => "Covers [{$found}], expected [{$expectedSourceFqcn}]",
                'linker' => $result['linker'],
            ];
        }

        return ['valid' => true, 'error' => null, 'linker' => $result['linker']];
    }

    /**
     * Legacy method: Verify using enforce_attribute (backward compatible).
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateTestAttribute(
        string $testFileAbsolutePath,
        string $expectedSourceFqcn,
        string $enforceAttribute
    ): array {
        // Delegate to the new linker system with a registry configured for the specific attribute
        $registry = CoverageLinkerRegistry::fromConfig(null, $enforceAttribute);
        $result = $this->validateCoverageLink($testFileAbsolutePath, $expectedSourceFqcn, $registry);

        // Map new error messages back to legacy format
        if (! $result['valid'] && $result['error'] === 'Missing coverage declaration (no covers/CoversClass found)') {
            return ['valid' => false, 'error' => "Missing attribute [{$enforceAttribute}]"];
        }

        return ['valid' => $result['valid'], 'error' => $result['error']];
    }

    /** @return array<string, string> short name => FQCN */
    public function extractUseMap(string $source): array
    {
        $map = [];
        if (preg_match_all('/\buse\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $fqcn = trim($match[1], '\\');
                $alias = isset($match[2]) ? $match[2] : (basename(str_replace('\\', '/', $fqcn)));
                $map[$alias] = $fqcn;
            }
        }

        return $map;
    }

    public function extractClassNamespace(string $source): ?string
    {
        if (preg_match('/\bnamespace\s+([\w\\\\]+)\s*;/', $source, $m)) {
            return trim($m[1], '\\');
        }

        return null;
    }

    protected function relativePathFromRoot(string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
        $path = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    /**
     * Extract the first declared class FQCN from PHP source (namespace + class).
     */
    protected function extractDeclaredClassFromSource(string $source): ?string
    {
        $namespace = null;
        if (preg_match('/\bnamespace\s+([\w\\\\]+)\s*;/', $source, $m)) {
            $namespace = trim($m[1], '\\');
        }
        if (preg_match('/\bclass\s+(\w+)/', $source, $m)) {
            $class = $m[1];

            return $namespace !== null ? $namespace . '\\' . $class : $class;
        }

        return null;
    }
}
