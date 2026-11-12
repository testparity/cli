<?php

// Specs: S003, S011

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/parity-pr-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->root.'/src');
    File::ensureDirectoryExists($this->root.'/tests');
    File::ensureDirectoryExists($this->root.'/.parity/per-test/reports');
    file_put_contents($this->root.'/src/Foo.php', "<?php\nreturn 1;\n");
    touch($this->root.'/tests/OwnerTest.php');
    file_put_contents($this->root.'/parity.yaml', <<<'YAML'
structure:
  - name: Services
    paths:
      source: src
      test: tests
    file_map:
      Foo.php: OwnerTest.php
    rules:
      - matched-coverage:
          min: 50
YAML);
    (new Process(['git', 'init', '-q'], $this->root))->mustRun();
    (new Process(['git', 'add', 'src'], $this->root))->mustRun();
    (new Process(['git', '-c', 'user.name=Parity', '-c', 'user.email=parity@example.invalid', 'commit', '-qm', 'Baseline'], $this->root))->mustRun();
    file_put_contents($this->root.'/src/Foo.php', "<?php\nreturn 2;\n");
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('uses the mapped test and reports unrelated-only and uncovered lines independently', function () {
    $owner = 'Tests\\OwnerTest';
    $other = 'Tests\\OwnerTestExtra';
    file_put_contents($this->root.'/src/Foo.php', "<?php\nif (false) { return 1; }\nreturn 2;\n");
    writePrTestReports($this->root, [$owner => [], $other => [3]]);
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(1)
        ->and($result['files'][0])->toMatchArray([
            'associated_test' => 'tests/OwnerTest.php',
            'associated_lines' => [],
            'unrelated_lines' => [3],
            'uncovered_lines' => [2],
            'matched_percent' => 0,
            'passed' => false,
        ]);

    writePrTestReports($this->root, [$owner => [3], $other => [3]]);
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(0)
        ->and($result['files'][0]['associated_lines'])->toBe([3])
        ->and($result['files'][0]['matched_percent'])->toBe(50);
});

it('keeps old reports and invalid or missing refs from looking like zero coverage', function () {
    writePrTestReports($this->root, ['Tests\\OwnerTest' => []], false);
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(2)
        ->and($result['files'][0]['status'])->toBe('attribution_unavailable')
        ->and($result['files'][0]['matched_percent'])->toBeNull();

    foreach (['no-such-base', ''] as $base) {
        [$exit, $result] = runParityPr($this->root, $base);
        expect($exit)->toBe(2)->and($result['status'])->toBe('error');
    }
});

it('rejects invalid matched thresholds instead of passing a zero threshold', function () {
    $config = $this->root.'/parity.yaml';
    file_put_contents($config, str_replace('min: 50', 'min: typo', file_get_contents($config)));
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(2)->and($result['status'])->toBe('error');
});

it('distinguishes non-executable changes and missing mapped tests', function () {
    $owner = 'Tests\\OwnerTest';
    file_put_contents($this->root.'/src/Foo.php', "<?php\nreturn 1;\n// added comment\n");
    writePrTestReports($this->root, [$owner => [2]], lines: [2]);
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(0)->and($result['files'][0]['status'])->toBe('not_applicable');

    unlink($this->root.'/tests/OwnerTest.php');
    file_put_contents($this->root.'/src/Foo.php', "<?php\nreturn 3;\n");
    writePrTestReports($this->root, ['Tests\\OtherTest' => [2]], lines: [2]);
    [$exit, $result] = runParityPr($this->root);

    expect($exit)->toBe(1)
        ->and($result['files'][0]['test_exists'])->toBeFalse()
        ->and($result['files'][0]['status'])->toBe('missing_test');
});

function runParityPr(string $root, string $base = 'HEAD'): array
{
    $code = Artisan::call('pr', [
        '--base' => $base,
        '--config' => $root.'/parity.yaml',
        '--format' => 'json',
    ]);

    return [$code, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

function writePrTestReports(string $root, array $coverage, bool $metadata = true, array $lines = [2, 3]): void
{
    $path = $root.'/.parity/per-test';
    $index = [];
    foreach ($coverage as $test => $hits) {
        $report = 'reports/'.count($index).'.json';
        $file = ['path' => 'src/Foo.php', 'coveredLines' => $hits, 'totalExecutableLines' => count($lines)];
        if ($metadata) {
            $file['executableLines'] = $lines;
        }
        file_put_contents($path.'/'.$report, json_encode(['version' => 1, 'test' => $test, 'files' => [$file]]));
        $index[] = ['test' => $test, 'path' => $report];
    }
    file_put_contents($path.'/index.json', json_encode([
        'version' => 1,
        'kind' => 'parity-per-test-coverage',
        'reports' => $index,
    ]));
}
