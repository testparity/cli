<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\RuleContext;
use PHPUnit\Framework\TestCase;

class RuleContextTest extends TestCase
{
    public function test_stores_rule_evaluation_inputs_as_readonly_values(): void
    {
        $context = new RuleContext(
            sourceAbsolutePath: '/project/app/Foo.php',
            sourceRelativePath: 'app/Foo.php',
            expectedSourceFqcn: 'App\\Foo',
            testAbsolutePath: '/project/tests/FooTest.php',
            testRelativePath: 'tests/FooTest.php',
            testExists: true,
            testContent: '<?php class FooTest {}',
            coveragePercent: 88.5,
            matchedCoveragePercent: 77.5,
            coveringTests: ['Tests\\FooTest::test_it_works'],
            projectRoot: '/project',
            expectedTestClassName: 'FooTest',
            lineCoverage: [10 => ['Tests\\FooTest::test_it_works']],
            totalExecutableLines: 1,
        );

        expect($context->sourceRelativePath)->toBe('app/Foo.php');
        expect($context->expectedSourceFqcn)->toBe('App\\Foo');
        expect($context->testExists)->toBeTrue();
        expect($context->coveragePercent)->toBe(88.5);
        expect($context->matchedCoveragePercent)->toBe(77.5);
        expect($context->lineCoverage[10])->toBe(['Tests\\FooTest::test_it_works']);
    }
}
