<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Specs: S002, S003
 *
 * Checks coverage from the matching test file only (not all tests).
 * Only meaningful when PHPUnit XML coverage data is available.
 */
class MatchedCoverageRule implements RuleInterface
{
    public function name(): string
    {
        return 'matched-coverage';
    }

    public function parameters(): array
    {
        return [
            'min' => 'sometimes|numeric|min:0|max:100',
        ];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        if (! $context->testExists || $context->expectedTestClassName === '') {
            return RuleResult::pass('-');
        }

        if ($context->totalExecutableLines === 0) {
            return RuleResult::pass('-');
        }

        // Compute matched coverage from line-level data
        $linesCoveredByMatch = 0;
        foreach ($context->lineCoverage as $lineTests) {
            foreach ($lineTests as $testName) {
                if (str_contains($testName, $context->expectedTestClassName)) {
                    $linesCoveredByMatch++;
                    break;
                }
            }
        }

        $percent = round(100.0 * $linesCoveredByMatch / $context->totalExecutableLines, 2);
        $min = isset($params['min']) ? (float) $params['min'] : null;

        if ($min !== null && $percent < $min) {
            return RuleResult::fail(
                "Matched coverage {$percent}% is below minimum {$min}%",
                "{$percent}%"
            );
        }

        return RuleResult::pass("{$percent}%");
    }

    public function columnHeader(): ?string
    {
        return 'Match';
    }

    public function formatCell(RuleResult $result): string
    {
        if ($result->value === '-') {
            return '<fg=gray>-</>';
        }

        if (! $result->passed) {
            return "<fg=red>{$result->value}</>";
        }

        return $result->value ?? '-';
    }

    public function isEnforced(): bool
    {
        // Only enforced when min is set
        return true;
    }
}
