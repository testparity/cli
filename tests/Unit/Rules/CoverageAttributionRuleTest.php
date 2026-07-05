<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\CoverageAttributionRule;
use App\Rules\RuleContext;
use App\Rules\RuleResult;
use PHPUnit\Framework\TestCase;

class CoverageAttributionRuleTest extends TestCase
{
    private CoverageAttributionRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CoverageAttributionRule;
    }

    public function test_reports_total_and_other_covering_test_counts(): void
    {
        $context = $this->makeContext([
            'Tests\\Unit\\FooTest::test_expected_path',
            'Tests\\Feature\\FooFeatureTest::test_other_path',
            'Tests\\Unit\\BarTest::test_unrelated_path',
        ]);

        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('3|2');
    }

    public function test_treats_all_tests_as_other_when_expected_test_class_is_unknown(): void
    {
        $context = $this->makeContext(
            coveringTests: ['Tests\\Feature\\SmokeTest::test_it_runs'],
            expectedTestClassName: '',
        );

        expect($this->rule->evaluate($context, [])->value)->toBe('1|1');
    }

    public function test_formats_empty_and_non_empty_counts_for_table_output(): void
    {
        expect($this->rule->formatCell(RuleResult::pass('0|0')))->toBe('<fg=gray>-</>');
        expect($this->rule->formatCell(RuleResult::pass('4|2')))->toBe('4');
        expect($this->rule->formatOtherCell(RuleResult::pass('4|0')))->toBe('<fg=gray>-</>');
        expect($this->rule->formatOtherCell(RuleResult::pass('4|2')))->toBe('<fg=yellow>2</>');
    }

    public function test_metadata_marks_rule_as_informational(): void
    {
        expect($this->rule->name())->toBe('coverage-attribution');
        expect($this->rule->columnHeader())->toBe('#');
        expect($this->rule->isEnforced())->toBeFalse();
    }

    private function makeContext(array $coveringTests, string $expectedTestClassName = 'FooTest'): RuleContext
    {
        return new RuleContext(
            sourceAbsolutePath: '/project/app/Foo.php',
            sourceRelativePath: 'app/Foo.php',
            expectedSourceFqcn: 'App\\Foo',
            testAbsolutePath: '/project/tests/Unit/FooTest.php',
            testRelativePath: 'tests/Unit/FooTest.php',
            testExists: true,
            testContent: '<?php class FooTest {}',
            coveragePercent: 100.0,
            matchedCoveragePercent: null,
            coveringTests: $coveringTests,
            projectRoot: '/project',
            expectedTestClassName: $expectedTestClassName,
        );
    }
}
