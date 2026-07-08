<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParityPerTestCoverageReader;
use PHPUnit\Framework\TestCase;

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
