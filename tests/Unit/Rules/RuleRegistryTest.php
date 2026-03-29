<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\MinimumCoverageRule;
use App\Rules\RuleRegistry;
use App\Rules\TestExistsRule;
use PHPUnit\Framework\TestCase;

class RuleRegistryTest extends TestCase
{
    private RuleRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new RuleRegistry;
    }

    // --- register / get / has / all ---

    public function test_registry_is_empty_by_default(): void
    {
        expect($this->registry->all())->toBe([]);
    }

    public function test_register_adds_rule(): void
    {
        $rule = new TestExistsRule;
        $this->registry->register($rule);

        expect($this->registry->has('test-exists'))->toBeTrue();
    }

    public function test_get_returns_registered_rule(): void
    {
        $rule = new TestExistsRule;
        $this->registry->register($rule);

        expect($this->registry->get('test-exists'))->toBe($rule);
    }

    public function test_get_returns_null_for_unknown_rule(): void
    {
        expect($this->registry->get('not-a-rule'))->toBeNull();
    }

    public function test_has_returns_false_for_unknown_rule(): void
    {
        expect($this->registry->has('not-a-rule'))->toBeFalse();
    }

    public function test_all_returns_all_registered_rules_keyed_by_name(): void
    {
        $testExists = new TestExistsRule;
        $minCoverage = new MinimumCoverageRule;

        $this->registry->register($testExists);
        $this->registry->register($minCoverage);

        $all = $this->registry->all();

        expect($all)->toHaveCount(2);
        expect($all['test-exists'])->toBe($testExists);
        expect($all['minimum-coverage'])->toBe($minCoverage);
    }

    public function test_registering_same_name_twice_replaces_previous(): void
    {
        $first = new TestExistsRule;
        $second = new TestExistsRule;

        $this->registry->register($first);
        $this->registry->register($second);

        expect($this->registry->all())->toHaveCount(1);
        expect($this->registry->get('test-exists'))->toBe($second);
    }

    // --- resolve: string format ---

    public function test_resolve_accepts_string_format_with_no_params(): void
    {
        $this->registry->register(new TestExistsRule);

        $resolved = $this->registry->resolve(['test-exists']);

        expect($resolved)->toHaveCount(1);
        expect($resolved[0]['rule'])->toBeInstanceOf(TestExistsRule::class);
        expect($resolved[0]['params'])->toBe([]);
    }

    // --- resolve: map format { rule-name: { param: value } } ---

    public function test_resolve_accepts_map_format_with_params(): void
    {
        $this->registry->register(new MinimumCoverageRule);

        $resolved = $this->registry->resolve([['minimum-coverage' => ['min' => 80]]]);

        expect($resolved)->toHaveCount(1);
        expect($resolved[0]['rule'])->toBeInstanceOf(MinimumCoverageRule::class);
        expect($resolved[0]['params'])->toBe(['min' => 80]);
    }

    public function test_resolve_accepts_named_format_with_name_key(): void
    {
        $this->registry->register(new MinimumCoverageRule);

        $resolved = $this->registry->resolve([['name' => 'minimum-coverage', 'min' => 90]]);

        expect($resolved)->toHaveCount(1);
        expect($resolved[0]['rule'])->toBeInstanceOf(MinimumCoverageRule::class);
        expect($resolved[0]['params'])->toBe(['min' => 90]);
    }

    public function test_resolve_returns_empty_params_when_map_value_is_not_array(): void
    {
        $this->registry->register(new TestExistsRule);

        // { test-exists: null } — non-array value falls back to []
        $resolved = $this->registry->resolve([['test-exists' => null]]);

        expect($resolved)->toHaveCount(1);
        expect($resolved[0]['params'])->toBe([]);
    }

    public function test_resolve_multiple_rules(): void
    {
        $this->registry->register(new TestExistsRule);
        $this->registry->register(new MinimumCoverageRule);

        $resolved = $this->registry->resolve([
            'test-exists',
            ['minimum-coverage' => ['min' => 75]],
        ]);

        expect($resolved)->toHaveCount(2);
        expect($resolved[0]['rule'])->toBeInstanceOf(TestExistsRule::class);
        expect($resolved[1]['rule'])->toBeInstanceOf(MinimumCoverageRule::class);
        expect($resolved[1]['params'])->toBe(['min' => 75]);
    }

    // --- resolve: error conditions ---

    public function test_resolve_throws_for_unknown_rule_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown parity rule/');

        $this->registry->resolve(['no-such-rule']);
    }

    public function test_resolve_error_message_lists_available_rules(): void
    {
        $this->registry->register(new TestExistsRule);

        try {
            $this->registry->resolve(['missing-rule']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            expect($e->getMessage())->toContain('test-exists');
        }
    }

    public function test_resolve_throws_when_required_param_missing(): void
    {
        $this->registry->register(new MinimumCoverageRule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/requires parameter 'min'/");

        // minimum-coverage requires 'min'
        $this->registry->resolve([['minimum-coverage' => []]]);
    }

    public function test_resolve_throws_when_numeric_param_is_not_numeric(): void
    {
        $this->registry->register(new MinimumCoverageRule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be numeric/');

        $this->registry->resolve([['minimum-coverage' => ['min' => 'not-a-number']]]);
    }

    public function test_resolve_skips_non_string_non_array_configs(): void
    {
        // Should silently skip rather than throw
        $resolved = $this->registry->resolve([42, true, null]);

        expect($resolved)->toBe([]);
    }

    // --- validateParams: dotted keys are skipped ---

    public function test_resolve_skips_dotted_param_keys(): void
    {
        // Register a rule whose parameter spec has a nested dotted key
        $ruleWithDotted = new class implements \App\Rules\RuleInterface {
            public function name(): string { return 'dotted-rule'; }

            public function parameters(): array
            {
                return [
                    'linkers.*' => 'string', // dotted — should be skipped by validator
                ];
            }

            public function evaluate(\App\Rules\RuleContext $ctx, array $params): \App\Rules\RuleResult
            {
                return \App\Rules\RuleResult::pass('ok');
            }

            public function columnHeader(): ?string { return null; }

            public function formatCell(\App\Rules\RuleResult $r): string { return ''; }

            public function isEnforced(): bool { return true; }
        };

        $this->registry->register($ruleWithDotted);

        // Should not throw even though 'linkers.*' looks required-ish
        $resolved = $this->registry->resolve(['dotted-rule']);

        expect($resolved)->toHaveCount(1);
    }
}
