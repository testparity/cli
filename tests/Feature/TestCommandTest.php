<?php

// Specs: S001, S003, S006

use Illuminate\Support\Facades\Artisan;

it('generates one parity report per test and runs check against it by default', function () {
    $root = createParityTestProject('parity-test-command');

    mkdir($root.'/src', 0777, true);
    mkdir($root.'/tests', 0777, true);

    file_put_contents($root.'/src/Foo.php', "<?php\nfunction foo(): int { return 1; }\n");
    file_put_contents($root.'/src/Bar.php', "<?php\nfunction bar(): int { return 2; }\n");
    file_put_contents($root.'/tests/FooTest.php', "<?php\n");
    file_put_contents($root.'/tests/BarTest.php', "<?php\n");

    file_put_contents($root.'/runner.php', <<<'PHP'
<?php
$testPath = $argv[1];
$coveragePath = $argv[2];
$testBase = basename($testPath);
$files = [];

if ($testBase === 'FooTest.php') {
    $files[] = [
        'path' => 'src/Foo.php',
        'coveragePercent' => 100,
        'totalExecutableLines' => 2,
        'lines' => [
            ['line' => 1, 'coveredBy' => ['Tests\\FooTest::test_owner']],
            ['line' => 2, 'coveredBy' => ['Tests\\FooTest::test_owner']],
        ],
    ];
}

if ($testBase === 'BarTest.php') {
    $files[] = [
        'path' => 'src/Bar.php',
        'coveragePercent' => 100,
        'totalExecutableLines' => 2,
        'lines' => [
            ['line' => 1, 'coveredBy' => ['Tests\\BarTest::test_owner']],
            ['line' => 2, 'coveredBy' => ['Tests\\BarTest::test_owner']],
        ],
    ];
}

file_put_contents($coveragePath, json_encode([
    'version' => 1,
    'globalPercent' => 100,
    'files' => $files,
], JSON_PRETTY_PRINT));
PHP);

    file_put_contents($root.'/parity.yaml', <<<'YAML'
settings:
  namespace_roots:
    src: Src
    tests: Tests
  source_extension: ".php"
  test_suffix: "Test"
  test_extension: ".php"
  namespace_separator: "\\"

coverage_xml: [.parity/per-test]

test:
  command: "php runner.php {test_abs} {coverage}"
  coverage: ".parity/tmp/{slug}.json"
  reports: ".parity/per-test"

structure:
  - name: Example
    paths:
      source: src
      test: tests
    rules:
      - minimum-coverage:
          min: 50
YAML);

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--format' => 'json',
    ]);

    expect($exitCode)->toBe(0);

    expect(is_file($root.'/.parity/per-test/index.json'))->toBeTrue();
    $manifest = json_decode((string) file_get_contents($root.'/.parity/per-test/index.json'), true);
    expect($manifest['reports'])->toHaveCount(2);

    $output = json_decode(Artisan::output(), true);
    expect($output['passed'])->toBeTrue();
    expect($output['structures'][0]['files'][0]['rules'])->toHaveKey('matched-coverage');
    expect($output['structures'][0]['files'][0]['rules'])->toHaveKey('coverage-attribution');
});

function createParityTestProject(string $name): string
{
    $root = sys_get_temp_dir().'/'.$name.'-'.bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    return $root;
}
