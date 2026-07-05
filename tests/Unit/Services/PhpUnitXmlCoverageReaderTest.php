<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PhpUnitXmlCoverageReader;
use PHPUnit\Framework\TestCase;

class PhpUnitXmlCoverageReaderTest extends TestCase
{
    // Specs: S003-FR-003, S003-FR-004, S010-FR-005

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/parity-phpunit-xml-reader-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir.'/project/app', 0777, true);
        mkdir($this->tempDir.'/coverage', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_reads_phpunit_xml_coverage_with_covering_tests_and_line_map(): void
    {
        file_put_contents($this->tempDir.'/project/app/Foo.php', '<?php echo "foo";');
        file_put_contents($this->tempDir.'/coverage/index.xml', $this->indexXml($this->tempDir.'/project/app'));
        file_put_contents($this->tempDir.'/coverage/Foo.php.xml', $this->fileXml());

        $result = (new PhpUnitXmlCoverageReader)->read($this->tempDir.'/coverage', $this->tempDir.'/project');
        $absolutePath = realpath($this->tempDir.'/project/app/Foo.php');

        expect($result['globalPercent'])->toBe(87.5);
        expect($result['coverage']['app/Foo.php'])->toBe(75.0);
        expect($result['coverage'][$absolutePath])->toBe(75.0);
        expect($result['testsByFile']['app/Foo.php'])->toBe([
            'Tests\\Unit\\FooTest::test_it_works',
            'Tests\\Feature\\FooFeatureTest::test_feature',
        ]);
        expect($result['lineCoverage']['app/Foo.php'][10])->toBe(['Tests\\Unit\\FooTest::test_it_works']);
        expect($result['totalExecutable']['app/Foo.php'])->toBe(4);
    }

    public function test_returns_empty_result_when_index_is_missing_or_invalid(): void
    {
        $reader = new PhpUnitXmlCoverageReader;

        expect($reader->read($this->tempDir.'/missing'))->toBe([
            'coverage' => [],
            'testsByFile' => [],
            'lineCoverage' => [],
            'totalExecutable' => [],
            'globalPercent' => null,
        ]);

        file_put_contents($this->tempDir.'/coverage/index.xml', '<coverage>');

        expect($reader->read($this->tempDir.'/coverage'))->toBe([
            'coverage' => [],
            'testsByFile' => [],
            'lineCoverage' => [],
            'totalExecutable' => [],
            'globalPercent' => null,
        ]);
    }

    private function indexXml(string $source): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage xmlns="https://schema.phpunit.de/coverage/1.0">
  <project source="{$source}">
    <directory name="/">
      <totals>
        <lines percent="87.50"/>
      </totals>
      <file href="Foo.php.xml"/>
    </directory>
  </project>
</coverage>
XML;
    }

    private function fileXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage xmlns="https://schema.phpunit.de/coverage/1.0">
  <file name="Foo.php" path="">
    <totals>
      <lines percent="75.00" executable="4"/>
    </totals>
    <coverage>
      <line nr="10">
        <covered by="Tests\Unit\FooTest::test_it_works"/>
      </line>
      <line nr="11">
        <covered by="Tests\Feature\FooFeatureTest::test_feature"/>
      </line>
    </coverage>
  </file>
</coverage>
XML;
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
