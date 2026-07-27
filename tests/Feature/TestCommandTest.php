<?php

// Specs: S001, S003, S006, S011

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

afterEach(function () {
    foreach ($GLOBALS['parity_test_project_roots'] ?? [] as $root) {
        (new Filesystem)->deleteDirectory($root);
    }
    $GLOBALS['parity_test_project_roots'] = [];
});

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
    file_put_contents($root.'/parity.test.yaml', "user-owned\n");

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
    expect(file_get_contents($root.'/parity.test.yaml'))->toBe("user-owned\n");
    expect(glob($root.'/.parity-test-*'))->toBe([]);
});

it('fails when a successful runner does not produce usable coverage', function () {
    $root = createSingleTestProject("<?php\nexit(0);\n");

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--no-check' => true,
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('did not contain executable source coverage');
    expect(is_dir($root.'/.parity/per-test'))->toBeFalse();
    expect(glob($root.'/.parity/tmp/*'))->toBe([]);
});

it('refuses to replace the project root or an unowned report directory', function () {
    $root = createSingleTestProject("<?php\nexit(0);\n");
    mkdir($root.'/build/reports', 0777, true);
    file_put_contents($root.'/build/reports/important.txt', "keep\n");

    $rootExitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--output' => '.',
        '--no-check' => true,
    ]);

    expect($rootExitCode)->toBe(1);
    expect(Artisan::output())->toContain('Refusing unsafe report directory');
    expect(is_file($root.'/src/Foo.php'))->toBeTrue();

    $unownedExitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--output' => 'build/reports',
        '--no-check' => true,
    ]);

    expect($unownedExitCode)->toBe(1);
    expect(Artisan::output())->toContain('Refusing to replace non-Parity directory');
    expect(file_get_contents($root.'/build/reports/important.txt'))->toBe("keep\n");
});

it('fails a test process that exceeds its configured timeout', function () {
    $root = createSingleTestProject("<?php\nusleep(500000);\n");

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--timeout' => '0.05',
        '--no-check' => true,
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('Timed out running test [tests/FooTest.php]');
    expect(is_dir($root.'/.parity/per-test'))->toBeFalse();
    expect(glob($root.'/.parity/tmp/*'))->toBe([]);
});

it('preserves the last complete report set when a runner fails', function () {
    $root = createSingleTestProject("<?php\nfwrite(STDERR, \"runner failed\\n\");\nexit(2);\n");
    mkdir($root.'/.parity/per-test/reports', 0777, true);
    $previousManifest = <<<'JSON'
{
  "version": 1,
  "kind": "parity-per-test-coverage",
  "reports": [
    {
      "test": "Tests\\PreviousTest",
      "path": "reports/previous.json"
    }
  ]
}
JSON;
    file_put_contents($root.'/.parity/per-test/index.json', $previousManifest);
    file_put_contents($root.'/.parity/per-test/reports/previous.json', "{}\n");

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--no-check' => true,
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('Failed running test [tests/FooTest.php]');
    expect(file_get_contents($root.'/.parity/per-test/index.json'))->toBe($previousManifest);
    expect(glob($root.'/.parity/per-test.tmp-*'))->toBe([]);
    expect(glob($root.'/.parity/tmp/*'))->toBe([]);
});

it('supports no-check mode and a safe output override', function () {
    $root = createSingleTestProject(validSingleTestRunner());

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--output' => '.parity/custom-reports',
        '--no-check' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Wrote parity per-test coverage reports to .parity/custom-reports');
    expect(is_file($root.'/.parity/custom-reports/index.json'))->toBeTrue();
    expect(glob($root.'/.parity-test-*'))->toBe([]);
});

it('fails fast when required test configuration is missing', function () {
    $root = createSingleTestProject(validSingleTestRunner());
    $config = (string) file_get_contents($root.'/parity.yaml');

    file_put_contents($root.'/parity.yaml', str_replace(
        '  command: "php runner.php {test_abs} {coverage}"'."\n",
        '',
        $config
    ));
    $commandExitCode = Artisan::call('test', ['--config' => $root.'/parity.yaml']);
    expect($commandExitCode)->toBe(1);
    expect(Artisan::output())->toContain('Missing test.command in parity.yaml');

    file_put_contents($root.'/parity.yaml', str_replace(
        '  coverage: ".parity/tmp/{slug}.json"'."\n",
        '',
        $config
    ));
    $coverageExitCode = Artisan::call('test', ['--config' => $root.'/parity.yaml']);
    expect($coverageExitCode)->toBe(1);
    expect(Artisan::output())->toContain('Missing test.coverage in parity.yaml');
});

it('uses an explicit file map when discovering the belonging test', function () {
    $root = createSingleTestProject(validSingleTestRunner());
    mkdir($root.'/tests/Ownership', 0777, true);
    rename($root.'/tests/FooTest.php', $root.'/tests/Ownership/FooOwnershipTest.php');

    $config = (string) file_get_contents($root.'/parity.yaml');
    $config = str_replace(
        "      test: tests\n    rules:",
        "      test: tests\n    file_map:\n      Foo.php: Ownership/FooOwnershipTest.php\n    rules:",
        $config
    );
    file_put_contents($root.'/parity.yaml', $config);

    $exitCode = Artisan::call('test', [
        '--config' => $root.'/parity.yaml',
        '--no-check' => true,
    ]);
    $manifest = json_decode((string) file_get_contents($root.'/.parity/per-test/index.json'), true);

    expect($exitCode)->toBe(0);
    expect($manifest['reports'])->toHaveCount(1);
    expect($manifest['reports'][0]['test'])->toContain('Ownership\\FooOwnershipTest');
});

function createParityTestProject(string $name): string
{
    $root = sys_get_temp_dir().'/'.$name.'-'.bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    $GLOBALS['parity_test_project_roots'][] = $root;

    return $root;
}

function createSingleTestProject(string $runner): string
{
    $root = createParityTestProject('parity-test-command-edge');
    mkdir($root.'/src', 0777, true);
    mkdir($root.'/tests', 0777, true);
    file_put_contents($root.'/src/Foo.php', "<?php\nfunction foo(): int { return 1; }\n");
    file_put_contents($root.'/tests/FooTest.php', "<?php\n");
    file_put_contents($root.'/runner.php', $runner);
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

    return $root;
}

function validSingleTestRunner(): string
{
    return <<<'PHP'
<?php

$coveragePath = $argv[2];
file_put_contents($coveragePath, json_encode([
    'version' => 1,
    'globalPercent' => 100,
    'files' => [
        [
            'path' => 'src/Foo.php',
            'coveragePercent' => 100,
            'totalExecutableLines' => 2,
            'lines' => [
                ['line' => 1, 'coveredBy' => ['Tests\\FooTest::test_owner']],
                ['line' => 2, 'coveredBy' => ['Tests\\FooTest::test_owner']],
            ],
        ],
    ],
], JSON_PRETTY_PRINT));
PHP;
}
