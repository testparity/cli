<?php

declare(strict_types=1);

// Specs: S002-FR-004, S003-FR-004, S008-FR-023, S010-FR-005

namespace Tests\Unit\Rules;

use App\Rules\MatchedCoverageRule;
use App\Rules\RuleContext;
use App\Rules\RuleResult;
use PHPUnit\Framework\TestCase;

class MatchedCoverageRuleTest extends TestCase
{
    private MatchedCoverageRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MatchedCoverageRule;
    }

    private function makeContext(
        bool $testExists = true,
        string $expectedTestClassName = 'FooTest',
        int $totalExecutableLines = 10,
        array $lineCoverage = [],
    ): RuleContext {
        return new RuleContext(
            sourceAbsolutePath: '/project/app/Foo.php',
            sourceRelativePath: 'app/Foo.php',
            expectedSourceFqcn: 'App\\Foo',
            testAbsolutePath: $testExists ? '/project/tests/Unit/FooTest.php' : null,
            testRelativePath: $testExists ? 'tests/Unit/FooTest.php' : null,
            testExists: $testExists,
            testContent: $testExists ? '<?php class FooTest {}' : null,
            coveragePercent: 0.0,
            matchedCoveragePercent: null,
            coveringTests: [],
            projectRoot: '/project',
            expectedTestClassName: $expectedTestClassName,
            lineCoverage: $lineCoverage,
            totalExecutableLines: $totalExecutableLines,
        );
    }

    public function test_name_returns_matched_coverage(): void
    {
        expect($this->rule->name())->toBe('matched-coverage');
    }

    public function test_parameters_defines_optional_min(): void
    {
        $params = $this->rule->parameters();

        expect($params)->toHaveKey('min');
        expect($params['min'])->toContain('sometimes');
        expect($params['min'])->toContain('numeric');
    }

    public function test_column_header(): void
    {
        expect($this->rule->columnHeader())->toBe('Match');
    }

    public function test_is_enforced(): void
    {
        expect($this->rule->isEnforced())->toBeTrue();
    }

    public function test_evaluate_passes_with_dash_when_test_does_not_exist(): void
    {
        $context = $this->makeContext(testExists: false);
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('-');
    }

    public function test_evaluate_passes_with_dash_when_no_expected_test_class_name(): void
    {
        $context = $this->makeContext(expectedTestClassName: '');
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('-');
    }

    public function test_evaluate_passes_with_dash_when_total_executable_lines_is_zero(): void
    {
        $context = $this->makeContext(totalExecutableLines: 0);
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('-');
    }

    public function test_evaluate_computes_matched_coverage_correctly(): void
    {
        // 8 of 10 lines covered by FooTest
        $lineCoverage = [];
        for ($i = 1; $i <= 8; $i++) {
            $lineCoverage[$i] = ['Tests\\Unit\\FooTest::test_something'];
        }
        // 2 lines covered by unrelated test only
        $lineCoverage[9] = ['Tests\\Unit\\BarTest::test_something'];
        $lineCoverage[10] = ['Tests\\Unit\\BarTest::test_other'];

        $context = $this->makeContext(
            totalExecutableLines: 10,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('80%');
    }

    public function test_evaluate_passes_when_all_lines_covered_by_matching_test(): void
    {
        $lineCoverage = [
            1 => ['Tests\\Unit\\FooTest::test_a'],
            2 => ['Tests\\Unit\\FooTest::test_b'],
            3 => ['Tests\\Unit\\FooTest::test_c'],
        ];

        $context = $this->makeContext(
            totalExecutableLines: 3,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('100%');
    }

    public function test_evaluate_passes_when_zero_lines_covered_and_no_min(): void
    {
        $context = $this->makeContext(
            totalExecutableLines: 10,
            lineCoverage: [],
        );
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('0%');
    }

    public function test_evaluate_fails_when_below_min(): void
    {
        // Only 2 of 10 lines covered by FooTest = 20%
        $lineCoverage = [
            1 => ['Tests\\Unit\\FooTest::test_a'],
            2 => ['Tests\\Unit\\FooTest::test_b'],
        ];

        $context = $this->makeContext(
            totalExecutableLines: 10,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, ['min' => 50]);

        expect($result->passed)->toBeFalse();
        expect($result->value)->toBe('20%');
        expect($result->error)->toBe('Matched coverage 20% is below minimum 50%');
    }

    public function test_evaluate_passes_when_meets_min_exactly(): void
    {
        // 5 of 10 lines covered by FooTest = 50%
        $lineCoverage = [];
        for ($i = 1; $i <= 5; $i++) {
            $lineCoverage[$i] = ['Tests\\Unit\\FooTest::test_a'];
        }

        $context = $this->makeContext(
            totalExecutableLines: 10,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, ['min' => 50]);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('50%');
    }

    public function test_evaluate_counts_line_only_once_even_with_multiple_matching_tests(): void
    {
        // Line 1 covered by two different FooTest methods — should count as 1
        $lineCoverage = [
            1 => ['Tests\\Unit\\FooTest::test_a', 'Tests\\Unit\\FooTest::test_b'],
        ];

        $context = $this->makeContext(
            totalExecutableLines: 4,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('25%');
    }

    public function test_evaluate_matches_by_substring_of_test_class_name(): void
    {
        // expectedTestClassName = 'FooTest' — should match 'Tests\Unit\FooTest::...'
        $lineCoverage = [
            1 => ['Tests\\Unit\\FooTest::test_a'],
            2 => ['SomeFooTestHelper::method'], // also contains 'FooTest'
        ];

        $context = $this->makeContext(
            totalExecutableLines: 4,
            lineCoverage: $lineCoverage,
        );
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('50%');
    }

    public function test_format_cell_returns_gray_dash_for_skipped(): void
    {
        $result = RuleResult::pass('-');

        expect($this->rule->formatCell($result))->toBe('<fg=gray>-</>');
    }

    public function test_format_cell_returns_red_value_when_failed(): void
    {
        $result = RuleResult::fail('below minimum', '20%');

        expect($this->rule->formatCell($result))->toBe('<fg=red>20%</>');
    }

    public function test_format_cell_returns_plain_value_when_passed(): void
    {
        $result = RuleResult::pass('80%');

        expect($this->rule->formatCell($result))->toBe('80%');
    }

    public function test_format_cell_returns_dash_when_value_is_null(): void
    {
        $result = RuleResult::pass(null);

        expect($this->rule->formatCell($result))->toBe('-');
    }
}
