<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Immutable context passed to every rule during evaluation.
 * Carries all the data a rule might need about a single source file.
 */
class RuleContext
{
    public function __construct(
        public readonly string $sourceAbsolutePath,
        public readonly string $sourceRelativePath,
        public readonly string $expectedSourceFqcn,
        public readonly ?string $testAbsolutePath,
        public readonly ?string $testRelativePath,
        public readonly bool $testExists,
        public readonly ?string $testContent,
        public readonly float $coveragePercent,
        public readonly ?float $matchedCoveragePercent,
        public readonly array $coveringTests,
        public readonly string $projectRoot,
        /** Expected test class name (e.g. "FooServiceTest") for matching */
        public readonly string $expectedTestClassName = '',
        /** Per-line coverage data: [lineNum => [testName, ...]] (PHPUnit XML only) */
        public readonly array $lineCoverage = [],
        /** Total executable lines for the source file */
        public readonly int $totalExecutableLines = 0,
    ) {}
}
