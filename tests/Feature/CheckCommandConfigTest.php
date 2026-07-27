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
  - name: Source
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
