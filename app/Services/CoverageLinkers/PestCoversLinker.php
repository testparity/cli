<?php

declare(strict_types=1);

namespace App\Services\CoverageLinkers;

/**
 * Specs: S004
 *
 * Extracts coverage declarations from Pest test files.
 * Supports: ->covers(Foo::class), ->coversClass(Foo::class)
 *           covers(Foo::class) (standalone file-level declaration)
 *
 * Pest files are plain PHP without a class declaration.
 * They use function calls like it(), test(), describe() with method chaining.
 */
class PestCoversLinker implements CoverageLinkerInterface
{
    public function supports(string $testFileContent): bool
    {
        // Pest files have no class declaration but use it()/test()/describe()
        $hasClass = (bool) preg_match('/\bclass\s+\w+\s*(extends|implements|\{)/', $testFileContent);
        $hasPestCalls = (bool) preg_match('/\b(?:it|test|describe|beforeEach|beforeAll)\s*\(/', $testFileContent);

        return ! $hasClass && $hasPestCalls;
    }

    public function extractCoveredClasses(string $source, array $useMap, ?string $namespace): array
    {
        $covered = [];

        // Match ->covers(...) and ->coversClass(...) (chained on test calls)
        // Match covers(...) (standalone file-level declaration)
        // These can contain one or more arguments: covers(Foo::class, Bar::class)
        if (preg_match_all('/(?:->)?covers(?:Class)?\s*\(([^)]+)\)/', $source, $matches)) {
            foreach ($matches[1] as $argList) {
                // Split by comma for multiple arguments
                $args = preg_split('/\s*,\s*/', trim($argList));
                foreach ($args as $arg) {
                    $arg = trim($arg);
                    $fqcn = PhpAttributeLinker::resolveClassReference($arg, $useMap, $namespace);
                    if ($fqcn !== null) {
                        $covered[] = $fqcn;
                    }
                }
            }
        }

        return array_values(array_unique($covered));
    }

    public function name(): string
    {
        return 'pest-covers';
    }
}
