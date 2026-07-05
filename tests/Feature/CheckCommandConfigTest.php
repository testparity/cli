<?php

// Specs: S001-FR-010, S002-FR-005, S010-FR-005

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
