<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Specs: S002, S007
 */
class TestExistsRule implements RuleInterface
{
    public function name(): string
    {
        return 'test-exists';
    }

    public function parameters(): array
    {
        return [];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        if ($context->testExists) {
            return RuleResult::pass('Y');
        }

        return RuleResult::fail('Test file not found', 'N');
    }

    public function columnHeader(): ?string
    {
        return '∃';
    }

    public function formatCell(RuleResult $result): string
    {
        return $result->passed
            ? '<fg=green>Y</>'
            : '<fg=red>N</>';
    }

    public function isEnforced(): bool
    {
        return true;
    }
}
