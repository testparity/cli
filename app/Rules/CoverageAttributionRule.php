<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Informational rule that shows which tests cover a source file.
 * Never fails — purely for visibility.
 */
class CoverageAttributionRule implements RuleInterface
{
    public function name(): string
    {
        return 'coverage-attribution';
    }

    public function parameters(): array
    {
        return [
            'show_names' => 'sometimes',
        ];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        $coveringTests = $context->coveringTests;
        $expectedTestClass = $context->expectedTestClassName;

        $otherTests = $expectedTestClass !== ''
            ? array_values(array_filter($coveringTests, fn (string $t): bool => ! str_contains($t, $expectedTestClass)))
            : $coveringTests;

        // Store both counts in value for formatting
        $totalCount = count($coveringTests);
        $otherCount = count($otherTests);

        return RuleResult::pass("{$totalCount}|{$otherCount}");
    }

    public function columnHeader(): ?string
    {
        // This rule contributes TWO columns — handled specially
        return '#';
    }

    public function formatCell(RuleResult $result): string
    {
        if ($result->value === null || $result->value === '0|0') {
            return '<fg=gray>-</>';
        }

        [$total, $other] = explode('|', $result->value);

        return (string) $total;
    }

    /**
     * Format the "Other" (non-matching) column.
     */
    public function formatOtherCell(RuleResult $result): string
    {
        if ($result->value === null) {
            return '<fg=gray>-</>';
        }

        [, $other] = explode('|', $result->value);

        if ($other === '0') {
            return '<fg=gray>-</>';
        }

        return "<fg=yellow>{$other}</>";
    }

    public function isEnforced(): bool
    {
        return false;
    }
}
