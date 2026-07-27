<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParityPerTestCoverageReader;
use PHPUnit\Framework\TestCase;

/**
 * Specs: S003, S011
 */
class ParityPerTestCoverageReaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/parity-per-test-reader-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir.'/project/src', 0777, true);
        mkdir($this->tempDir.'/project/.parity/reports', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_merges_one_report_per_test_into_attribution_data(): void
    {
        file_put_contents($this->tempDir.'/project/src/Foo.php', '<?php echo "foo";');
        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\Unit\\FooTest', 'path' => 'reports/foo.json'],
                ['test' => 'Tests\\Unit\\BarTest', 'path' => 'reports/bar.json'],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($this->tempDir.'/project/.parity/reports/foo.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\FooTest',
            'files' => [
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 4,
                    'coveredLines' => [1, 2],
                ],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($this->tempDir.'/project/.parity/reports/bar.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\BarTest',
            'files' => [
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 4,
                    'coveredLines' => [2, 4],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $result = (new ParityPerTestCoverageReader)->read($this->tempDir.'/project/.parity', $this->tempDir.'/project');
        $absolutePath = realpath($this->tempDir.'/project/src/Foo.php');

        expect($result['coverage']['src/Foo.php'])->toBe(75.0);
        expect($result['coverage'][$absolutePath])->toBe(75.0);
        expect($result['totalExecutable']['src/Foo.php'])->toBe(4);
        expect($result['testsByFile']['src/Foo.php'])->toBe(['Tests\\Unit\\FooTest', 'Tests\\Unit\\BarTest']);
        expect($result['lineCoverage']['src/Foo.php'][2])->toBe(['Tests\\Unit\\FooTest', 'Tests\\Unit\\BarTest']);
        expect($result['globalPercent'])->toBe(75.0);
    }

    public function test_rejects_manifest_report_paths_outside_the_reports_directory(): void
    {
        file_put_contents($this->tempDir.'/project/outside.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\OutsideTest',
            'files' => [
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 1,
                    'coveredLines' => [1],
                ],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\Unit\\OutsideTest', 'path' => '../outside.json'],
            ],
        ], JSON_PRETTY_PRINT));

        $result = (new ParityPerTestCoverageReader)->read($this->tempDir.'/project/.parity', $this->tempDir.'/project');

        expect($result['coverage'])->toBe([]);
        expect($result['lineCoverage'])->toBe([]);
        expect($result['globalPercent'])->toBeNull();
    }

    public function test_rejects_a_reports_directory_symlink_that_escapes_the_manifest_root(): void
    {
        $externalReports = $this->tempDir.'/project/external-reports';
        mkdir($externalReports, 0777, true);
        file_put_contents($externalReports.'/outside.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\OutsideTest',
            'files' => [],
        ], JSON_PRETTY_PRINT));
        rmdir($this->tempDir.'/project/.parity/reports');
        if (! @symlink($externalReports, $this->tempDir.'/project/.parity/reports')) {
            $this->markTestSkipped('Symbolic links are unavailable in this environment.');
        }
        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\Unit\\OutsideTest', 'path' => 'reports/outside.json'],
            ],
        ], JSON_PRETTY_PRINT));

        $result = (new ParityPerTestCoverageReader)->read($this->tempDir.'/project/.parity', $this->tempDir.'/project');

        expect($result['coverage'])->toBe([]);
        expect($result['globalPercent'])->toBeNull();
    }

    public function test_rejects_unknown_schema_versions_and_mismatched_report_tests(): void
    {
        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 2,
            'kind' => 'parity-per-test-coverage',
            'reports' => [],
        ], JSON_PRETTY_PRINT));

        $reader = new ParityPerTestCoverageReader;
        expect($reader->read($this->tempDir.'/project/.parity', $this->tempDir.'/project')['coverage'])->toBe([]);

        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\Unit\\ExpectedTest', 'path' => 'reports/mismatch.json'],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($this->tempDir.'/project/.parity/reports/mismatch.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\DifferentTest',
            'files' => [],
        ], JSON_PRETTY_PRINT));

        expect($reader->read($this->tempDir.'/project/.parity', $this->tempDir.'/project')['coverage'])->toBe([]);
    }

    public function test_does_not_attribute_a_test_that_covered_no_lines(): void
    {
        file_put_contents($this->tempDir.'/project/.parity/index.json', json_encode([
            'version' => 1,
            'kind' => 'parity-per-test-coverage',
            'reports' => [
                ['test' => 'Tests\\Unit\\FooTest', 'path' => 'reports/foo.json'],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($this->tempDir.'/project/.parity/reports/foo.json', json_encode([
            'version' => 1,
            'test' => 'Tests\\Unit\\FooTest',
            'files' => [
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 4,
                    'coveredLines' => [],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $result = (new ParityPerTestCoverageReader)->read($this->tempDir.'/project/.parity', $this->tempDir.'/project');

        expect($result['coverage']['src/Foo.php'])->toBe(0.0);
        expect($result['testsByFile']['src/Foo.php'])->toBe([]);
        expect($result['globalPercent'])->toBe(0.0);
    }

    private function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

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
            is_dir($child) && ! is_link($child) ? $this->removeDirectory($child) : unlink($child);
        }

        rmdir($path);
    }
}
