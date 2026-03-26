<?php

declare(strict_types=1);

namespace App\Services;

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
     * Verify the test class has the required attribute pointing to the expected source class.
     * Validates by parsing the file source only; does not load the test file (avoids triggering Pest/phpunit).
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateTestAttribute(
        string $testFileAbsolutePath,
        string $expectedSourceFqcn,
        string $enforceAttribute
    ): array {
        $testFqcn = $this->getFqcnFromTestFile($testFileAbsolutePath);
        if ($testFqcn === null) {
            return ['valid' => false, 'error' => 'Could not determine test class name'];
        }

        if (! is_file($testFileAbsolutePath)) {
            return ['valid' => false, 'error' => 'Test file not found'];
        }

        $content = @file_get_contents($testFileAbsolutePath);
        if ($content === false) {
            return ['valid' => false, 'error' => 'Could not read test file'];
        }

        $coveredFqcn = $this->extractCoversClassFromSource($content, $enforceAttribute);
        if ($coveredFqcn === null) {
            return ['valid' => false, 'error' => "Missing attribute [{$enforceAttribute}]"];
        }

        if ($coveredFqcn !== $expectedSourceFqcn) {
            return [
                'valid' => false,
                'error' => "Attribute points to [{$coveredFqcn}], expected [{$expectedSourceFqcn}]",
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Extract the CoversClass (or equivalent) attribute value from PHP source.
     * Supports PHPUnit\Framework\Attributes\CoversClass and short CoversClass with use.
     * Returns the FQCN string or null if not found.
     */
    protected function extractCoversClassFromSource(string $source, string $enforceAttribute): ?string
    {
        $useMap = $this->extractUseMap($source);
        $classNamespace = $this->extractClassNamespace($source);
        $attributeShortName = $this->attributeShortName($enforceAttribute);

        // Only consider the part of the file before the first "class " (test class)
        $classPos = preg_match('/\bclass\s+\w+/', $source, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($source);
        $beforeClass = substr($source, 0, $classPos);

        // Find all #[ ... ] blocks and look for CoversClass in any
        $len = strlen($beforeClass);
        $i = 0;
        while ($i < $len) {
            if ($beforeClass[$i] === '#' && $i + 1 < $len && $beforeClass[$i + 1] === '[') {
                $depth = 1;
                $start = $i + 2;
                $j = $i + 2;
                while ($j < $len && $depth > 0) {
                    if ($beforeClass[$j] === '[') {
                        $depth++;
                    } elseif ($beforeClass[$j] === ']') {
                        $depth--;
                        if ($depth === 0) {
                            $attributeContent = substr($beforeClass, $start, $j - $start);
                            $covered = $this->parseCoversClassFromBlock($attributeContent, $attributeShortName, $useMap, $classNamespace);
                            if ($covered !== null) {
                                return $covered;
                            }
                            $i = $j + 1;
                            break;
                        }
                    }
                    $j++;
                }
                if ($depth !== 0) {
                    $i = $j;
                }
                continue;
            }
            $i++;
        }

        return null;
    }

    private function parseCoversClassFromBlock(
        string $attributeContent,
        string $attributeShortName,
        array $useMap,
        ?string $classNamespace
    ): ?string {
        // Attribute block can contain multiple attributes. Look for CoversClass(...)
        $pattern = '/\b' . preg_quote($attributeShortName, '/') . '\s*\(/';
        if (! preg_match($pattern, $attributeContent, $argMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $open = $argMatch[0][1] + strlen($argMatch[0][0]) - 1; // offset of '('
        $arg = $this->extractBalancedParens($attributeContent, $open);
        if ($arg === null) {
            return null;
        }
        $arg = trim(preg_replace('/\s+/', ' ', $arg));

        // String literal: 'FQCN' or "FQCN"
        if (preg_match("/^['\"]([^'\"]+)['\"]\s*$/", $arg, $strMatch)) {
            return str_replace('\\\\', '\\', $strMatch[1]);
        }

        // X::class or \FQCN::class (leading backslash = global)
        if (preg_match('/^(\\\\?)(\w+(?:\\\\\w+)*)::class\s*$/', $arg, $classMatch)) {
            $ref = str_replace('\\\\', '\\', $classMatch[2]);
            if ($classMatch[1] === '\\') {
                return $ref;
            }
            $parts = explode('\\', $ref);
            $first = $parts[0];
            if (isset($useMap[$first])) {
                $base = $useMap[$first];
                array_shift($parts);
                return $parts === [] ? $base : $base . '\\' . implode('\\', $parts);
            }
            if ($classNamespace !== null) {
                return $classNamespace . '\\' . $ref;
            }
            return $ref;
        }

        return null;
    }

    /** @return array<string, string> short name => FQCN */
    protected function extractUseMap(string $source): array
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

    protected function extractClassNamespace(string $source): ?string
    {
        if (preg_match('/\bnamespace\s+([\w\\\\]+)\s*;/', $source, $m)) {
            return trim($m[1], '\\');
        }
        return null;
    }

    protected function attributeShortName(string $enforceAttribute): string
    {
        $parts = explode('\\', $enforceAttribute);
        return end($parts);
    }

    /** Extract content inside balanced (...) starting at offset; return null if unbalanced. */
    protected function extractBalancedParens(string $source, int $offset): ?string
    {
        $len = strlen($source);
        if ($offset >= $len || $source[$offset] !== '(') {
            return null;
        }
        $depth = 1;
        $start = $offset + 1;
        for ($i = $start; $i < $len; $i++) {
            $c = $source[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start);
                }
            }
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
