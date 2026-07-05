<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\EnforceCoverageLinkRule;
use App\Rules\RuleContext;
use App\Rules\RuleResult;
use PHPUnit\Framework\TestCase;

class EnforceCoverageLinkRuleTest extends TestCase
{
    private EnforceCoverageLinkRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EnforceCoverageLinkRule;
    }

    public function test_passes_when_test_file_declares_expected_pest_coverage_link(): void
    {
        $root = $this->makeProjectRoot();

        try {
            $testPath = $root.'/tests/Unit/FooTest.php';
            mkdir(dirname($testPath), 0777, true);
            file_put_contents($testPath, <<<'PHP'
<?php

use App\Services\Foo;

it('covers foo', function () {})->covers(Foo::class);
PHP);

            $result = $this->rule->evaluate($this->makeContext($root, $testPath), []);

            expect($result->passed)->toBeTrue();
            expect($result->value)->toBe('Y');
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function test_fails_when_test_file_has_no_coverage_declaration(): void
    {
        $root = $this->makeProjectRoot();

        try {
            $testPath = $root.'/tests/Unit/FooTest.php';
            mkdir(dirname($testPath), 0777, true);
            file_put_contents($testPath, "<?php\n\nit('runs', function () {});\n");

            $result = $this->rule->evaluate($this->makeContext($root, $testPath), []);

            expect($result->passed)->toBeFalse();
            expect($result->value)->toBe('N');
            expect($result->error)->toBe('Missing coverage declaration (no covers/CoversClass found)');
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function test_skips_when_matching_test_file_is_missing(): void
    {
        $result = $this->rule->evaluate(new RuleContext(
            sourceAbsolutePath: '/project/app/Services/Foo.php',
            sourceRelativePath: 'app/Services/Foo.php',
            expectedSourceFqcn: 'App\\Services\\Foo',
            testAbsolutePath: null,
            testRelativePath: null,
            testExists: false,
            testContent: null,
            coveragePercent: 0.0,
            matchedCoveragePercent: null,
            coveringTests: [],
            projectRoot: '/project',
        ), []);

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('-');
    }

    public function test_formats_link_status_for_table_output(): void
    {
        expect($this->rule->formatCell(RuleResult::pass('Y')))->toBe('<fg=green>Y</>');
        expect($this->rule->formatCell(RuleResult::fail('missing', 'N')))->toBe('<fg=red>N</>');
        expect($this->rule->formatCell(RuleResult::skip('-')))->toBe('<fg=gray>-</>');
    }

    public function test_metadata_describes_required_rule_contract(): void
    {
        expect($this->rule->name())->toBe('enforce-coverage-link');
        expect($this->rule->columnHeader())->toBe('Link');
        expect($this->rule->isEnforced())->toBeTrue();
        expect($this->rule->parameters())->toHaveKeys(['linkers', 'linkers.*', 'attribute']);
    }

    private function makeContext(string $root, string $testPath): RuleContext
    {
        return new RuleContext(
            sourceAbsolutePath: $root.'/app/Services/Foo.php',
            sourceRelativePath: 'app/Services/Foo.php',
            expectedSourceFqcn: 'App\\Services\\Foo',
            testAbsolutePath: $testPath,
            testRelativePath: 'tests/Unit/FooTest.php',
            testExists: true,
            testContent: file_get_contents($testPath),
            coveragePercent: 100.0,
            matchedCoveragePercent: null,
            coveringTests: [],
            projectRoot: $root,
        );
    }

    private function makeProjectRoot(): string
    {
        $root = sys_get_temp_dir().'/parity-coverage-link-rule-'.bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path.'/'.$item;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }

        rmdir($path);
    }
}
