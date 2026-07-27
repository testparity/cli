<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParityTestArtifactNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Specs: S003, S011
 */
final class ParityTestArtifactNormalizerTest extends TestCase
{
    public function test_it_normalizes_single_test_parity_json_into_per_test_report_shape(): void
    {
        $root = sys_get_temp_dir().'/parity-normalizer-'.bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        try {
            $coveragePath = $root.'/coverage.json';
            file_put_contents($coveragePath, json_encode([
                'version' => 1,
                'globalPercent' => 100,
                'files' => [
                    [
                        'path' => 'src/Foo.php',
                        'coveragePercent' => 100,
                        'totalExecutableLines' => 3,
                        'lines' => [
                            ['line' => 2, 'coveredBy' => ['Tests\\Unit\\Services\\FooTest::test_it_works']],
                            ['line' => 3, 'coveredBy' => ['Tests\\Unit\\Services\\FooTest::test_it_works']],
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $normalizer = new ParityTestArtifactNormalizer;
            $report = $normalizer->normalize($coveragePath, 'Tests\\Unit\\Services\\FooTest', $root);

            $this->assertSame(1, $report['version']);
            $this->assertSame('Tests\\Unit\\Services\\FooTest', $report['test']);
            $this->assertSame([
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 3,
                    'coveredLines' => [2, 3],
                ],
            ], $report['files']);
        } finally {
            removeNormalizerTempDirectory($root);
        }
    }

    public function test_it_converts_absolute_clover_paths_to_unique_project_relative_paths(): void
    {
        $root = sys_get_temp_dir().'/parity-normalizer-'.bin2hex(random_bytes(4));
        mkdir($root.'/src', 0777, true);
        file_put_contents($root.'/src/Foo.php', "<?php\nreturn 1;\n");

        try {
            $coveragePath = $root.'/clover.xml';
            $sourcePath = htmlspecialchars($root.'/src/Foo.php', ENT_XML1);
            file_put_contents($coveragePath, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="{$sourcePath}">
      <line num="2" type="stmt" count="1"/>
      <metrics statements="1" coveredstatements="1"/>
    </file>
  </project>
</coverage>
XML);

            $report = (new ParityTestArtifactNormalizer)->normalize($coveragePath, 'Tests\\FooTest', $root);

            $this->assertSame([
                [
                    'path' => 'src/Foo.php',
                    'totalExecutableLines' => 1,
                    'coveredLines' => [2],
                ],
            ], $report['files']);
        } finally {
            removeNormalizerTempDirectory($root);
        }
    }
}

function removeNormalizerTempDirectory(string $path): void
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
        is_dir($child) ? removeNormalizerTempDirectory($child) : unlink($child);
    }

    rmdir($path);
}
