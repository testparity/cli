<?php

declare(strict_types=1);

namespace App\Rules;

interface RuleInterface
{
    /**
     * Unique identifier used in parity.yaml and container binding.
     * e.g. 'test-exists', 'minimum-coverage', 'enforce-coverage-link'
     */
    public function name(): string;

    /**
     * Laravel Validator rules for parameters this rule accepts in parity.yaml.
     * e.g. ['min' => 'required|numeric|min:0|max:100']
     *
     * @return array<string, string|array>
     */
    public function parameters(): array;

    /**
     * Evaluate this rule for a single source file.
     */
    public function evaluate(RuleContext $context, array $params): RuleResult;

    /**
     * Column header for the parity check table. Null = no column.
     */
    public function columnHeader(): ?string;

    /**
     * Format the cell value for the table output.
     */
    public function formatCell(RuleResult $result): string;

    /**
     * Whether this rule contributes to pass/fail.
     */
    public function isEnforced(): bool;
}
