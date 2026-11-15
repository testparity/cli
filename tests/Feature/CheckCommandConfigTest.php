<?php

// Specs: S001-FR-010, S002-FR-005, S010-FR-005

use Illuminate\Support\Facades\Artisan;

it('fails cleanly when config yaml is invalid', function () {
    $root = createTemporaryParityProject('invalid-yaml');
    file_put_contents($root.'/parity.yaml', 'structure: [');

    try {
        $this->artisan('check', [
            '--config' => $root.'/parity.yaml',
            '--format' => 'json',
        ])->assertExitCode(1);
    } finally {
        removeTemporaryParityProject($root);
    }
});

it('fails cleanly when a configured rule is unknown', function () {
    $root = createTemporaryParityProject('unknown-rule');
    mkdir($root.'/src', 0777, true);
    mkdir($root.'/tests', 0777, true);
    file_put_contents($root.'/src/Foo.php', '<?php echo "foo";');
    file_put_contents($root.'/tests/FooTest.php', '<?php echo "foo test";');
    file_put_contents($root.'/clover.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="{$root}/src/Foo.php">
      <metrics statements="1" coveredstatements="1"/>
    </file>
    <metrics files="1" statements="1" coveredstatements="1"/>
  </project>
</coverage>
XML);
    file_put_contents($root.'/parity.yaml', <<<'YAML'
coverage_xml: clover.xml
structure:
  - name: Example
    paths:
      source: src
      test: tests
    rules:
      - no-such-rule
YAML);

    try {
        $this->artisan('check', [
            '--config' => $root.'/parity.yaml',
            '--format' => 'json',
        ])->assertExitCode(1);
    } finally {
        removeTemporaryParityProject($root);
    }
});

it('reports aggregate table status from rule-specific thresholds', function () {
    $root = createTemporaryParityProject('summary-thresholds');
    mkdir($root.'/src', 0777, true);
    mkdir($root.'/tests', 0777, true);
    mkdir($root.'/contracts', 0777, true);
    mkdir($root.'/contract-tests', 0777, true);
    file_put_contents($root.'/src/Foo.php', "<?php\nreturn 1;\n");
    file_put_contents($root.'/tests/FooTest.php', "<?php\n");
    file_put_contents($root.'/contracts/Marker.php', "<?php\ninterface Marker {}\n");
    file_put_contents($root.'/contract-tests/MarkerTest.php', "<?php\n");
    file_put_contents($root.'/clover.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="{$root}/src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="0"/>
      <metrics statements="2" coveredstatements="1"/>
    </file>
    <file name="{$root}/contracts/Marker.php">
      <metrics statements="0" coveredstatements="0"/>
    </file>
    <metrics files="2" statements="2" coveredstatements="1"/>
  </project>
</coverage>
XML);
    file_put_contents($root.'/parity.yaml', <<<'YAML'
coverage_xml: clover.xml
min_coverage: 80
structure:
  - name: "Source | <example>"
    paths:
      source: src
      test: tests
    rules:
      - minimum-coverage:
          min: 40
  - name: Contracts
    paths:
      source: contracts
      test: contract-tests
    rules:
      - minimum-coverage:
          min: 0
YAML);

    try {
        $exitCode = Artisan::call('check', ['--config' => $root.'/parity.yaml']);
        $output = (string) preg_replace('/\e\[[\d;]*m/', '', Artisan::output());

        expect($exitCode)->toBe(0);
        expect($output)->toMatch('/Per-file min \(all tests\)\s+\|\s+50\.00%\s+\|\s+Per rule\s+\|\s+OK/');

        $exitCode = Artisan::call('check', [
            '--config' => $root.'/parity.yaml',
            '--format' => 'github',
            '--no-ansi' => true,
        ]);
        expect($exitCode)->toBe(0);
        expect(Artisan::output())
            ->toStartWith('## TestParity coverage')
            ->toContain('### <code>Source &#124; &lt;example&gt;</code>')
            ->toContain('| <code>src/Foo.php</code> | <code>tests/FooTest.php</code> | 50% |')
            ->not->toContain('Per-file min');

        unlink($root.'/tests/FooTest.php');
        $exitCode = Artisan::call('check', [
            '--config' => $root.'/parity.yaml',
            '--format' => 'github',
        ]);
        expect($exitCode)->toBe(1);
        expect(Artisan::output())->toContain('**Failed**', '<code>test-exists</code>');
    } finally {
        removeTemporaryParityProject($root);
    }
});

function createTemporaryParityProject(string $name): string
{
    $root = sys_get_temp_dir().'/parity-check-command-'.$name.'-'.bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    return $root;
}

function removeTemporaryParityProject(string $path): void
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
        is_dir($child) ? removeTemporaryParityProject($child) : unlink($child);
    }

    rmdir($path);
}

it('matches attributed tests to structures in non-PHP projects', function () {
    $root = createTemporaryParityProject('unmatched-ts');
    mkdir($root.'/src', 0777, true);
    mkdir($root.'/tests', 0777, true);
    file_put_contents($root.'/src/sum.ts', "export const sum = (a: number, b: number) => a + b\n");
    file_put_contents($root.'/tests/sum.test.ts', "// sum test\n");
    file_put_contents($root.'/parity-coverage.json', json_encode([
        'version' => 1,
        'globalPercent' => 100,
        'files' => [[
            'path' => 'src/sum.ts',
            'coveragePercent' => 100,
            'totalExecutableLines' => 1,
            'lines' => [['line' => 1, 'coveredBy' => ['Tests.sum.test::adds', 'Tests.stray.test::orphan']]],
        ]],
    ]));
    file_put_contents($root.'/parity.yaml', <<<'YAML'
settings:
  namespace_roots:
    src: Src
    tests: Tests
  source_extension: ".ts"
  test_suffix: ".test"
  test_extension: ".ts"
  namespace_separator: "."
coverage_xml: parity-coverage.json
structure:
  - name: Source
    paths:
      source: src
      test: tests
    rules:
      - matched-coverage
YAML);

    try {
        Artisan::call('check', ['--config' => $root.'/parity.yaml']);
        $output = (string) preg_replace('/\e\[[\d;]*m/', '', Artisan::output());

        expect($output)->toContain('Tests that did not match any structure');
        expect($output)->toContain('Tests.stray.test');
        expect($output)->not->toContain('Tests.sum.test');
        expect($output)->not->toContain('.php');
    } finally {
        removeTemporaryParityProject($root);
    }
});

it('compares full-suite and exact associated-test line coverage in GitHub output', function () {
    $root = createTemporaryParityProject('suite-comparison');
    mkdir($root.'/src');
    mkdir($root.'/tests');
    mkdir($root.'/per-test/reports', 0777, true);
    file_put_contents($root.'/src/Foo.php', '<?php');
    file_put_contents($root.'/tests/FooTest.php', '<?php');
    file_put_contents($root.'/suite.xml', <<<'XML'
<coverage><project><file name="src/Foo.php">
    <line num="1" type="stmt" count="1"/>
    <line num="2" type="stmt" count="1"/>
    <line num="3" type="stmt" count="0"/>
</file></project></coverage>
XML);
    file_put_contents($root.'/parity.yaml', <<<'YAML'
coverage_xml: per-test
structure:
  - name: Example
    paths:
      source: src
      test: tests
YAML);
    $report = static fn (string $test, array $covered, array $executable = [1, 2, 3]): string => json_encode([
        'version' => 1,
        'test' => $test,
        'files' => [[
            'path' => 'src/Foo.php',
            'totalExecutableLines' => count($executable),
            'coveredLines' => $covered,
            'executableLines' => $executable,
        ]],
    ], JSON_THROW_ON_ERROR);
    $test = 'Tests\\FooTest';
    $similar = 'Tests\\OtherFooTest';
    file_put_contents($root.'/per-test/index.json', json_encode([
        'version' => 1,
        'kind' => 'parity-per-test-coverage',
        'reports' => [
            ['test' => $test, 'path' => 'reports/foo.json'],
            ['test' => $similar, 'path' => 'reports/other.json'],
        ],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($root.'/per-test/reports/foo.json', $report($test, [1]));
    file_put_contents($root.'/per-test/reports/other.json', $report($similar, [2]));

    try {
        $arguments = ['--config' => $root.'/parity.yaml', '--format' => 'github', '--suite-coverage' => 'suite.xml'];
        Artisan::call('check', $arguments);
        expect(Artisan::output())
            ->toContain('| Full suite | Associated test only | Gap |')
            ->toContain('| 66.67% | 33.33% | 33.33 pp |');

        file_put_contents($root.'/per-test/reports/foo.json', $report($test, [1], [1, 2, 4]));
        Artisan::call('check', $arguments);
        expect(Artisan::output())->toContain('| 66.67% | — | — |');

        file_put_contents($root.'/per-test/reports/foo.json', $report($test, []));
        Artisan::call('check', $arguments);
        expect(Artisan::output())->toContain('| 66.67% | 0% | 66.67 pp |');

        $arguments['--suite-coverage'] = 'missing.xml';
        expect(Artisan::call('check', $arguments))->toBe(1);
        expect(Artisan::output())->toContain('No file-level coverage data');
    } finally {
        removeTemporaryParityProject($root);
    }
});

it('omits interface-only files without inferred tests and still checks mapped or executable files', function () {
    $root = createTemporaryParityProject('interface-declarations');
    mkdir($root.'/src');
    mkdir($root.'/tests');
    file_put_contents($root.'/src/Unmapped.php', '<?php namespace App; interface Unmapped {}');
    file_put_contents($root.'/src/Mapped.php', '<?php namespace App; interface Mapped {}');
    file_put_contents($root.'/src/Observed.php', '<?php namespace App; interface Observed {}');
    file_put_contents($root.'/src/Implementation.php', '<?php namespace App; interface Client {} class Implementation implements Client {}');
    file_put_contents($root.'/clover.xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <coverage>
          <project>
            <file name="src/Observed.php">
              <line num="1" type="stmt" count="0"/>
              <metrics statements="1" coveredstatements="0"/>
            </file>
            <metrics files="1" statements="1" coveredstatements="0"/>
          </project>
        </coverage>
        XML);
    file_put_contents($root.'/parity.yaml', <<<'YAML'
        coverage_xml: clover.xml
        structure:
          - name: Source
            paths:
              source: src
              test: tests
            file_map:
              Mapped.php: ContractTest.php
        YAML);

    try {
        $options = ['--config' => $root.'/parity.yaml', '--format' => 'github'];

        expect(Artisan::call('check', $options))->toBe(1);
        expect(Artisan::output())
            ->toContain(
                '**Failed**',
                '<code>tests/ContractTest.php</code>',
                '<code>tests/ImplementationTest.php</code>',
                '<code>tests/ObservedTest.php</code>',
                '<code>test-exists</code>',
            )
            ->not->toContain('Unmapped.php');

        file_put_contents($root.'/tests/ContractTest.php', '<?php');
        file_put_contents($root.'/tests/ImplementationTest.php', '<?php');

        rename($root.'/clover.xml', $root.'/suite.xml');
        file_put_contents($root.'/clover.xml', '<coverage><project/></coverage>');
        expect(Artisan::call('check', $options + ['--suite-coverage' => $root.'/suite.xml']))->toBe(1);
        expect(Artisan::output())->toContain('<code>tests/ObservedTest.php</code>', '<code>test-exists</code>');

        file_put_contents($root.'/tests/ObservedTest.php', '<?php');

        expect(Artisan::call('check', $options))->toBe(0);
        expect(Artisan::output())->toContain('**Passed**')->not->toContain('Unmapped.php');
    } finally {
        removeTemporaryParityProject($root);
    }
});
