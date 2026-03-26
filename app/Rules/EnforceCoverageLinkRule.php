<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\CoverageLinkers\CoverageLinkerRegistry;
use App\Services\ParityChecker;

class EnforceCoverageLinkRule implements RuleInterface
{
    public function name(): string
    {
        return 'enforce-coverage-link';
    }

    public function parameters(): array
    {
        return [
            'linkers' => 'sometimes|array',
            'linkers.*' => 'string|in:auto,pest-covers,php-attribute',
            'attribute' => 'sometimes|string',
        ];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        if (! $context->testExists || $context->testContent === null) {
            return RuleResult::pass('-');
        }

        $linkerNames = $params['linkers'] ?? null;
        $attributeFqcn = $params['attribute'] ?? 'PHPUnit\Framework\Attributes\CoversClass';

        $registry = CoverageLinkerRegistry::fromConfig($linkerNames, $attributeFqcn);

        $checker = new ParityChecker(
            new \App\Services\NamespaceHelper,
            $context->projectRoot
        );

        $result = $checker->validateCoverageLink(
            $context->testAbsolutePath,
            $context->expectedSourceFqcn,
            $registry
        );

        if ($result['valid']) {
            return RuleResult::pass('Y');
        }

        return RuleResult::fail($result['error'] ?? 'Missing coverage link', 'N');
    }

    public function columnHeader(): ?string
    {
        return 'Link';
    }

    public function formatCell(RuleResult $result): string
    {
        if ($result->value === '-') {
            return '<fg=gray>-</>';
        }

        return $result->passed
            ? '<fg=green>Y</>'
            : '<fg=red>N</>';
    }

    public function isEnforced(): bool
    {
        return true;
    }
}
