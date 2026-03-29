<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\RuleContext;
use App\Rules\RuleResult;
use App\Rules\TestExistsRule;
use PHPUnit\Framework\TestCase;

class TestExistsRuleTest extends TestCase
{
    private TestExistsRule $rule;

    protected function setUp(): void
    {
        $this->rule = new TestExistsRule;
    }

    private function makeContext(bool $testExists): RuleContext
    {
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
        );
    }

    public function test_name_returns_test_exists(): void
    {
        expect($this->rule->name())->toBe('test-exists');
    }

    public function test_parameters_returns_empty_array(): void
    {
        expect($this->rule->parameters())->toBe([]);
    }

    public function test_column_header(): void
    {
        expect($this->rule->columnHeader())->toBe('∃');
    }

    public function test_is_enforced(): void
    {
        expect($this->rule->isEnforced())->toBeTrue();
    }

    public function test_evaluate_passes_when_test_exists(): void
    {
        $context = $this->makeContext(testExists: true);
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('Y');
        expect($result->error)->toBeNull();
    }

    public function test_evaluate_fails_when_test_does_not_exist(): void
    {
        $context = $this->makeContext(testExists: false);
        $result = $this->rule->evaluate($context, []);

        expect($result->passed)->toBeFalse();
        expect($result->value)->toBe('N');
        expect($result->error)->toBe('Test file not found');
    }

    public function test_format_cell_green_when_passed(): void
    {
        $result = RuleResult::pass('Y');

        expect($this->rule->formatCell($result))->toBe('<fg=green>Y</>');
    }

    public function test_format_cell_red_when_failed(): void
    {
        $result = RuleResult::fail('Test file not found', 'N');

        expect($this->rule->formatCell($result))->toBe('<fg=red>N</>');
    }
}
