<?php

declare(strict_types=1);

namespace App\Services\CoverageLinkers;

/**
 * Specs: S004
 *
 * Extracts coverage declarations from PHP 8 attributes.
 * Supports: #[CoversClass(Foo::class)]
 */
class PhpAttributeLinker implements CoverageLinkerInterface
{
    private string $attributeFqcn;

    private string $attributeShortName;

    public function __construct(string $attributeFqcn = 'PHPUnit\Framework\Attributes\CoversClass')
    {
        $this->attributeFqcn = $attributeFqcn;
        $parts = explode('\\', $attributeFqcn);
        $this->attributeShortName = end($parts);
    }

    public function supports(string $testFileContent): bool
    {
        // PHP attribute linker works on files with a class declaration
        return (bool) preg_match('/\bclass\s+\w+/', $testFileContent);
    }

    public function extractCoveredClasses(string $source, array $useMap, ?string $namespace): array
    {
        // Only consider the part of the file before the first "class " (test class)
        $classPos = preg_match('/\bclass\s+\w+/', $source, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($source);
        $beforeClass = substr($source, 0, $classPos);

        $covered = [];
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
                            $fqcn = $this->parseCoversClassFromBlock($attributeContent, $useMap, $namespace);
                            if ($fqcn !== null) {
                                $covered[] = $fqcn;
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

        return $covered;
    }

    public function name(): string
    {
        return 'php-attribute';
    }

    private function parseCoversClassFromBlock(string $attributeContent, array $useMap, ?string $classNamespace): ?string
    {
        $pattern = '/\b'.preg_quote($this->attributeShortName, '/').'\s*\(/';
        if (! preg_match($pattern, $attributeContent, $argMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $open = $argMatch[0][1] + strlen($argMatch[0][0]) - 1;
        $arg = $this->extractBalancedParens($attributeContent, $open);
        if ($arg === null) {
            return null;
        }
        $arg = trim(preg_replace('/\s+/', ' ', $arg));

        return $this->resolveClassReference($arg, $useMap, $classNamespace);
    }

    /**
     * Resolve a class reference from an attribute/method argument.
     * Supports: 'FQCN', "FQCN", X::class, \FQCN::class
     */
    public static function resolveClassReference(string $arg, array $useMap, ?string $classNamespace): ?string
    {
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

                return $parts === [] ? $base : $base.'\\'.implode('\\', $parts);
            }
            if ($classNamespace !== null) {
                return $classNamespace.'\\'.$ref;
            }

            return $ref;
        }

        return null;
    }

    private function extractBalancedParens(string $source, int $offset): ?string
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
}
