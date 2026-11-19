<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParityJsonCoverageReader;
use PHPUnit\Framework\TestCase;

class ParityJsonCoverageReaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/parity-json-reader-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir.'/project/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_reads_language_neutral_per_test_attribution(): void
    {
        file_put_contents($this->tempDir.'/project/src/Price.ts', 'export const price = 1');
        file_put_contents($this->tempDir.'/project/parity-coverage.json', json_encode([
            'version' => 1,
            'globalPercent' => 80,
            'files' => [
                [
                    'path' => 'src/Price.ts',
                    'coveragePercent' => 70,
                    'totalExecutableLines' => 10,
                    'lines' => [
                        ['line' => 1, 'coveredBy' => ['PriceTest::formats_price']],
                        ['line' => 2, 'coveredBy' => ['PriceTest::formats_price', 'CheckoutFlowTest::renders_total']],
                        ['line' => 3, 'coveredBy' => ['CheckoutFlowTest::renders_total']],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $result = (new ParityJsonCoverageReader)->read($this->tempDir.'/project/parity-coverage.json', $this->tempDir.'/project');
        $absolutePath = realpath($this->tempDir.'/project/src/Price.ts');

        expect($result['globalPercent'])->toBe(80.0);
        expect($result['coverage']['src/Price.ts'])->toBe(70.0);
        expect($result['coverage'][$absolutePath])->toBe(70.0);
        expect($result['totalExecutable']['src/Price.ts'])->toBe(10);
        expect($result['lineCoverage']['src/Price.ts'][2])->toBe(['PriceTest::formats_price', 'CheckoutFlowTest::renders_total']);
        expect($result['testsByFile']['src/Price.ts'])->toBe(['PriceTest::formats_price', 'CheckoutFlowTest::renders_total']);
    }

    public function test_returns_empty_result_for_missing_or_invalid_json(): void
    {
        $reader = new ParityJsonCoverageReader;
        $empty = ['coverage' => [], 'testsByFile' => [], 'lineCoverage' => [], 'totalExecutable' => [], 'globalPercent' => null];

        expect($reader->read($this->tempDir.'/missing.json'))->toBe($empty);

        file_put_contents($this->tempDir.'/invalid.json', '{');

        expect($reader->read($this->tempDir.'/invalid.json'))->toBe($empty);
    }

    public function test_exposes_only_complete_explicit_attribution_as_executable_evidence(): void
    {
        $path = $this->tempDir.'/coverage.json';
        $valid = [
            'path' => 'src/Price.ts',
            'totalExecutableLines' => 2,
            'lines' => [
                ['line' => 1, 'coveredBy' => ['PriceTest::formats_price']],
                ['line' => 2, 'coveredBy' => []],
            ],
        ];
        $cases = [
            [$valid, [1, 2]],
            [array_replace($valid, ['totalExecutableLines' => 'garbage']), null],
            [array_replace($valid, ['totalExecutableLines' => 2.5]), null],
            [array_replace($valid, ['totalExecutableLines' => '2']), null],
            [array_replace($valid, ['lines' => [['line' => 1], ['line' => 2, 'coveredBy' => []]]]), null],
            [array_replace($valid, ['lines' => [['line' => 1, 'coveredBy' => 'invalid'], ['line' => 2, 'coveredBy' => []]]]), null],
            [array_replace($valid, ['lines' => [['line' => 1, 'coveredBy' => [42]], ['line' => 2, 'coveredBy' => []]]]), null],
            [array_replace($valid, ['lines' => [['line' => 1, 'coveredBy' => []], ['line' => 1, 'coveredBy' => []]]]), null],
            [array_replace($valid, ['lines' => [['line' => 0, 'coveredBy' => []], ['line' => 2, 'coveredBy' => []]]]), null],
            [array_replace($valid, ['totalExecutableLines' => 0, 'lines' => []]), []],
        ];
        foreach ($cases as [$file, $expected]) {
            file_put_contents($path, json_encode(['files' => [$file]]));
            $result = (new ParityJsonCoverageReader)->read($path, $this->tempDir.'/project');
            if ($expected === null) {
                expect($result['executableLines'])->not->toHaveKey('src/Price.ts');
            } else {
                expect($result['executableLines']['src/Price.ts'])->toBe($expected);
                expect($result['executableLines'][$this->tempDir.'/project/src/Price.ts'])->toBe($expected);
            }
        }
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
