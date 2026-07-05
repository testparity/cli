<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CoverageReader;
use PHPUnit\Framework\TestCase;

class CoverageReaderTest extends TestCase
{
    // Specs: S003-FR-001, S003-FR-002, S003-FR-017, S010-FR-005

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/parity-coverage-reader-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir.'/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_reads_per_file_and_global_coverage_from_clover_xml(): void
    {
        file_put_contents($this->tempDir.'/src/Foo.php', '<?php echo "foo";');
        file_put_contents($this->tempDir.'/clover.xml', $this->cloverXml($this->tempDir.'/src/Foo.php'));

        $reader = new CoverageReader;

        $coverage = $reader->read($this->tempDir.'/clover.xml', $this->tempDir);

        expect($coverage[realpath($this->tempDir.'/src/Foo.php')])->toBe(50.0);
        expect($coverage['src/Foo.php'])->toBe(50.0);
        expect($reader->readGlobalCoverage($this->tempDir.'/clover.xml'))->toBe(50.0);
    }

    public function test_treats_files_with_no_statements_as_fully_covered(): void
    {
        file_put_contents($this->tempDir.'/src/Empty.php', '<?php');
        file_put_contents($this->tempDir.'/clover.xml', $this->cloverXml($this->tempDir.'/src/Empty.php', 0, 0));

        $coverage = (new CoverageReader)->read($this->tempDir.'/clover.xml', $this->tempDir);

        expect($coverage['src/Empty.php'])->toBe(100.0);
    }

    public function test_prefers_clover_path_attribute_when_name_is_only_a_basename(): void
    {
        file_put_contents($this->tempDir.'/src/sum.js', 'module.exports = { sum: (a, b) => a + b }');
        file_put_contents($this->tempDir.'/clover.xml', $this->cloverXmlWithPathAttribute('sum.js', $this->tempDir.'/src/sum.js'));

        $coverage = (new CoverageReader)->read($this->tempDir.'/clover.xml', $this->tempDir);

        expect($coverage[realpath($this->tempDir.'/src/sum.js')])->toBe(100.0);
        expect($coverage['src/sum.js'])->toBe(100.0);
    }

    public function test_reads_per_file_and_global_coverage_from_cobertura_xml(): void
    {
        file_put_contents($this->tempDir.'/src/Foo.ts', 'export const foo = 1');
        file_put_contents($this->tempDir.'/cobertura.xml', $this->coberturaXml($this->tempDir.'/src/Foo.ts'));

        $reader = new CoverageReader;

        $coverage = $reader->read($this->tempDir.'/cobertura.xml', $this->tempDir);

        expect($coverage[realpath($this->tempDir.'/src/Foo.ts')])->toBe(75.0);
        expect($coverage['src/Foo.ts'])->toBe(75.0);
        expect($reader->readGlobalCoverage($this->tempDir.'/cobertura.xml'))->toBe(80.0);
    }

    public function test_reads_cobertura_line_hits_when_class_line_rate_is_missing(): void
    {
        file_put_contents($this->tempDir.'/src/Bar.rs', 'pub fn bar() {}');
        file_put_contents($this->tempDir.'/cobertura.xml', $this->coberturaXmlWithoutClassRate($this->tempDir.'/src/Bar.rs'));

        $coverage = (new CoverageReader)->read($this->tempDir.'/cobertura.xml', $this->tempDir);

        expect($coverage['src/Bar.rs'])->toBe(66.67);
    }

    public function test_returns_empty_values_for_missing_or_invalid_clover_xml(): void
    {
        $reader = new CoverageReader;

        expect($reader->read($this->tempDir.'/missing.xml', $this->tempDir))->toBe([]);
        expect($reader->readGlobalCoverage($this->tempDir.'/missing.xml'))->toBeNull();

        file_put_contents($this->tempDir.'/invalid.xml', '<coverage>');

        expect($reader->read($this->tempDir.'/invalid.xml', $this->tempDir))->toBe([]);
        expect($reader->readGlobalCoverage($this->tempDir.'/invalid.xml'))->toBeNull();
    }

    private function cloverXml(string $fileName, int $statements = 4, int $coveredStatements = 2): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="{$fileName}">
      <metrics statements="{$statements}" coveredstatements="{$coveredStatements}"/>
    </file>
    <metrics files="1" statements="{$statements}" coveredstatements="{$coveredStatements}"/>
  </project>
</coverage>
XML;
    }

    private function cloverXmlWithPathAttribute(string $name, string $path): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="{$name}" path="{$path}">
      <metrics statements="2" coveredstatements="2"/>
    </file>
    <metrics files="1" statements="2" coveredstatements="2"/>
  </project>
</coverage>
XML;
    }

    private function coberturaXml(string $fileName): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage line-rate="0.8">
  <packages>
    <package name="src">
      <classes>
        <class name="Foo" filename="{$fileName}" line-rate="0.75">
          <lines>
            <line number="1" hits="1"/>
            <line number="2" hits="1"/>
            <line number="3" hits="1"/>
            <line number="4" hits="0"/>
          </lines>
        </class>
      </classes>
    </package>
  </packages>
</coverage>
XML;
    }

    private function coberturaXmlWithoutClassRate(string $fileName): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage line-rate="0.66">
  <packages>
    <package name="src">
      <classes>
        <class name="Bar" filename="{$fileName}">
          <lines>
            <line number="1" hits="1"/>
            <line number="2" hits="0"/>
            <line number="3" hits="1"/>
          </lines>
        </class>
      </classes>
    </package>
  </packages>
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
