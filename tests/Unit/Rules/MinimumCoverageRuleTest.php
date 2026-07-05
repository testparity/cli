<?php

declare(strict_types=1);

// Specs: S002-FR-004, S003-FR-001, S010-FR-005

namespace Tests\Unit\Rules;

use App\Rules\MinimumCoverageRule;
use App\Rules\RuleContext;
use App\Rules\RuleResult;
use PHPUnit\Framework\TestCase;

class MinimumCoverageRuleTest extends TestCase
{
    private MinimumCoverageRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MinimumCoverageRule;
    }

    private function makeContext(float $coveragePercent): RuleContext
    {
        return new RuleContext(
            sourceAbsolutePath: '/project/app/Foo.php',
            sourceRelativePath: 'app/Foo.php',
            expectedSourceFqcn: 'App\\Foo',
            testAbsolutePath: '/project/tests/Unit/FooTest.php',
            testRelativePath: 'tests/Unit/FooTest.php',
            testExists: true,
            testContent: '<?php class FooTest {}',
            coveragePercent: $coveragePercent,
            matchedCoveragePercent: null,
            coveringTests: [],
            projectRoot: '/project',
        );
    }

    public function test_name_returns_minimum_coverage(): void
    {
        expect($this->rule->name())->toBe('minimum-coverage');
    }

    public function test_parameters_defines_min(): void
    {
        $params = $this->rule->parameters();

        expect($params)->toHaveKey('min');
        expect($params['min'])->toContain('required');
        expect($params['min'])->toContain('numeric');
    }

    public function test_column_header(): void
    {
        expect($this->rule->columnHeader())->toBe('Cov');
    }

    public function test_is_enforced(): void
    {
        expect($this->rule->isEnforced())->toBeTrue();
    }

    public function test_evaluate_passes_when_coverage_meets_minimum(): void
    {
        $context = $this->makeContext(80.0);
        $result = $this->rule->evaluate($context, ['min' => 80]);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('80%');
    }

    public function test_evaluate_passes_when_coverage_exceeds_minimum(): void
    {
        $context = $this->makeContext(95.5);
        $result = $this->rule->evaluate($context, ['min' => 80]);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('95.5%');
    }

    public function test_evaluate_fails_when_coverage_below_minimum(): void
    {
        $context = $this->makeContext(65.0);
        $result = $this->rule->evaluate($context, ['min' => 80]);

        expect($result->passed)->toBeFalse();
        expect($result->value)->toBe('65%');
        expect($result->error)->toBe('Coverage 65% is below minimum 80%');
    }

    public function test_evaluate_defaults_to_80_when_min_param_missing(): void
    {
        $context = $this->makeContext(79.0);
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeFalse();
        expect($result->error)->toBe('Coverage 79% is below minimum 80%');
    }

    public function test_evaluate_at_exactly_zero_with_zero_minimum(): void
    {
        $context = $this->makeContext(0.0);
        $result = $this->rule->evaluate($context, ['min' => 0]);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('0%');
    }

    public function test_evaluate_at_100_percent(): void
    {
        $context = $this->makeContext(100.0);
        $result = $this->rule->evaluate($context, ['min' => 100]);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('100%');
    }

    public function test_format_cell_green_when_passed(): void
    {
        $result = RuleResult::pass('85%');

        expect($this->rule->formatCell($result))->toBe('<fg=green>85%</>');
    }

    public function test_format_cell_red_when_failed(): void
    {
        $result = RuleResult::fail('Coverage 60% is below minimum 80%', '60%');

        expect($this->rule->formatCell($result))->toBe('<fg=red>60%</>');
    }
}
