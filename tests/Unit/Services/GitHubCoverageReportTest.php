<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GitHubCoverageReport;
use PHPUnit\Framework\TestCase;

final class GitHubCoverageReportTest extends TestCase
{
    public function test_report_distinguishes_attribution_and_rule_warnings(): void
    {
        $report = (new GitHubCoverageReport)->render([
            'passed' => true,
            'global_coverage' => 80,
            'min_coverage_global' => 70,
            'structures' => [[
                'name' => '[@team](link) | <unsafe>',
                'files' => [[
                    'source' => 'app/Foo.php',
                    'test' => 'tests/FooTest.php',
                    'passed' => true,
                    'coverage' => 80,
                    'rules' => [
                        'matched-coverage' => ['passed' => true, 'value' => '30%'],
                        'custom-rule' => ['passed' => false],
                    ],
                ]],
            ]],
        ]);

        self::assertStringContainsString('Reported global coverage: 80% (minimum 70%)', $report);
        self::assertStringContainsString('&#91;&#64;team&#93;(link) &#124; &lt;unsafe&gt;', $report);
        self::assertStringContainsString('| 80% | <code>30%</code> | Passed with warnings · <code>custom-rule</code> |', $report);
    }
}
