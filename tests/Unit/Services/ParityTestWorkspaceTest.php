<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParityTestWorkspace;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Specs: S011
 */
final class ParityTestWorkspaceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir().'/parity-test-workspace-'.bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $this->root = realpath($root) ?: $root;
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
    }

    public function test_it_rejects_destructive_or_unowned_report_targets(): void
    {
        $workspace = new ParityTestWorkspace($this->root);

        try {
            $workspace->reportTarget($this->root);
            $this->fail('The project root must not be accepted as a report target.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing unsafe report directory', $e->getMessage());
        }

        mkdir($this->root.'/build/reports', 0777, true);
        file_put_contents($this->root.'/build/reports/important.txt', "keep\n");

        try {
            $workspace->reportTarget($this->root.'/build/reports');
            $this->fail('An unowned non-empty directory must not be accepted as a report target.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing to replace non-Parity directory', $e->getMessage());
        }

        $this->assertSame("keep\n", file_get_contents($this->root.'/build/reports/important.txt'));
    }

    public function test_it_marks_and_cleans_generated_coverage_artifacts(): void
    {
        $workspace = new ParityTestWorkspace($this->root);
        file_put_contents($this->root.'/existing.xml', '<important/>');

        try {
            $workspace->prepareCoverageArtifact($this->root.'/existing.xml');
            $this->fail('An unowned existing artifact must not be replaced.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing to replace unowned coverage artifact', $e->getMessage());
        }
        $this->assertSame('<important/>', file_get_contents($this->root.'/existing.xml'));

        $coveragePath = $workspace->prepareCoverageArtifact($this->root.'/build/coverage.xml');

        $this->assertFileExists($coveragePath.'.parity-test-artifact');
        file_put_contents($coveragePath, '<coverage/>');

        $workspace->removePreparedCoverageArtifact($coveragePath);

        $this->assertFileDoesNotExist($coveragePath);
        $this->assertFileDoesNotExist($coveragePath.'.parity-test-artifact');
    }

    public function test_it_rejects_symlink_ancestors_and_report_overlap(): void
    {
        $workspace = new ParityTestWorkspace($this->root);
        mkdir($this->root.'/.parity', 0777, true);
        mkdir($this->root.'/linked', 0777, true);
        symlink($this->root.'/linked', $this->root.'/.parity/link');

        try {
            $workspace->reportTarget($this->root.'/.parity/link/reports');
            $this->fail('A report target beneath a symlink must not be accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing symlink component in report directory', $e->getMessage());
        }

        try {
            $workspace->prepareCoverageArtifact($this->root.'/.parity/link/coverage.xml');
            $this->fail('A coverage target beneath a symlink must not be accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing symlink component in coverage artifact', $e->getMessage());
        }

        $reportsDir = $workspace->reportTarget($this->root.'/.parity/per-test');
        try {
            $workspace->prepareCoverageArtifact($reportsDir.'/index.json', $reportsDir);
            $this->fail('A coverage target must not overlap the report directory.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('must not overlap the report directory', $e->getMessage());
        }
    }

    public function test_it_publishes_a_complete_staged_report_set(): void
    {
        $workspace = new ParityTestWorkspace($this->root);
        $reportsDir = $this->root.'/.parity/per-test';
        mkdir($reportsDir.'/reports', 0777, true);
        file_put_contents($reportsDir.'/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [],
        ], JSON_PRETTY_PRINT));

        $target = $workspace->reportTarget($reportsDir);
        $staging = $workspace->createStagingDirectory($target);
        $workspace->writeJsonFile($staging.'/index.json', [
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\FooTest', 'path' => 'reports/foo.json'],
            ],
        ]);
        $workspace->writeJsonFile($staging.'/reports/foo.json', [
            'version' => 1,
            'test' => 'Tests\\FooTest',
            'files' => [
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 1,
                    'coveredLines' => [1],
                ],
            ],
        ]);

        $warning = $workspace->publishReports($staging, $target);
        $manifest = json_decode((string) file_get_contents($target.'/index.json'), true);

        $this->assertNull($warning);
        $this->assertSame('Tests\\FooTest', $manifest['reports'][0]['test']);
        $this->assertFileExists($target.'/reports/foo.json');
        $this->assertSame([], glob($target.'.previous-*'));
    }
}
