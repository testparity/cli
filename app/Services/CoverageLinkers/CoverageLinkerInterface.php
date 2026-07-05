<?php

declare(strict_types=1);

namespace App\Services\CoverageLinkers;

/**
 * Specs: S004, S005
 */
interface CoverageLinkerInterface
{
    /**
     * Can this linker handle the given test file?
     */
    public function supports(string $testFileContent): bool;

    /**
     * Extract the FQCN(s) this test file declares it covers.
     *
     * @return list<string> Fully qualified class names
     */
    public function extractCoveredClasses(string $source, array $useMap, ?string $namespace): array;

    /**
     * Human-readable name for error messages.
     */
    public function name(): string;
}
