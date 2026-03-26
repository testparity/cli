<?php

declare(strict_types=1);

namespace App\Rules;

class MinimumCoverageRule implements RuleInterface
{
    public function name(): string
    {
        return 'minimum-coverage';
    }

    public function parameters(): array
    {
        return [
            'min' => 'required|numeric|min:0|max:100',
        ];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        $min = (float) ($params['min'] ?? 80);
        $percent = $context->coveragePercent;

        if ($percent >= $min) {
            return RuleResult::pass("{$percent}%");
        }

        return RuleResult::fail(
            "Coverage {$percent}% is below minimum {$min}%",
            "{$percent}%"
        );
    }

    public function columnHeader(): ?string
    {
        return 'Cov';
    }

    public function formatCell(RuleResult $result): string
    {
        $color = $result->passed ? 'green' : 'red';

        return "<fg={$color}>{$result->value}</>";
    }

    public function isEnforced(): bool
    {
        return true;
    }
}
