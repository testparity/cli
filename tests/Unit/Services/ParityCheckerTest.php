<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CoverageLinkers\CoverageLinkerRegistry;
use App\Services\NamespaceHelper;
use App\Services\ParityChecker;
use PHPUnit\Framework\TestCase;

class ParityCheckerTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir().'/parity-checker-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/tests/Unit', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function test_gets_declared_test_class_from_source_file(): void
    {
        $path = $this->projectRoot.'/tests/Unit/RealNameTest.php';
        file_put_contents($path, <<<'PHP'
<?php

namespace Tests\Unit;

class RealNameTest {}
PHP);

        $checker = new ParityChecker(new NamespaceHelper, $this->projectRoot);

        expect($checker->getFqcnFromTestFile($path))->toBe('Tests\\Unit\\RealNameTest');
    }

    public function test_falls_back_to_path_based_class_name_when_no_class_is_declared(): void
    {
        $path = $this->projectRoot.'/tests/Unit/ClosureTest.php';
        file_put_contents($path, "<?php\n\nit('runs', function () {});\n");

        $checker = new ParityChecker(new NamespaceHelper, $this->projectRoot);

        expect($checker->getFqcnFromTestFile($path))->toBe('Tests\\Unit\\ClosureTest');
    }

    public function test_validates_pest_coverage_link_against_expected_source_class(): void
    {
        $path = $this->projectRoot.'/tests/Unit/FooTest.php';
        file_put_contents($path, <<<'PHP'
<?php

use App\Services\Foo;

it('covers foo', function () {})->covers(Foo::class);
PHP);

        $checker = new ParityChecker(new NamespaceHelper, $this->projectRoot);

        expect($checker->validateCoverageLink($path, 'App\\Services\\Foo', new CoverageLinkerRegistry))->toMatchArray([
            'valid' => true,
            'error' => null,
            'linker' => 'pest-covers',
        ]);
    }

    public function test_reports_missing_or_mismatched_coverage_links(): void
    {
        $path = $this->projectRoot.'/tests/Unit/FooTest.php';
        file_put_contents($path, "<?php\n\nit('runs', function () {});\n");

        $checker = new ParityChecker(new NamespaceHelper, $this->projectRoot);
        $missing = $checker->validateCoverageLink('/missing/FooTest.php', 'App\\Services\\Foo', new CoverageLinkerRegistry);
        $mismatched = $checker->validateCoverageLink($path, 'App\\Services\\Foo', new CoverageLinkerRegistry);

        expect($missing['valid'])->toBeFalse();
        expect($missing['error'])->toBe('Test file not found');
        expect($mismatched['valid'])->toBeFalse();
        expect($mismatched['error'])->toBe('Missing coverage declaration (no covers/CoversClass found)');
    }

    public function test_extracts_use_map_and_namespace_from_php_source(): void
    {
        $source = <<<'PHP'
<?php

namespace Tests\Unit;

use App\Services\Foo;
use App\Services\Bar as Baz;
PHP;

        $checker = new ParityChecker(new NamespaceHelper, $this->projectRoot);

        expect($checker->extractUseMap($source))->toBe([
            'Foo' => 'App\\Services\\Foo',
            'Baz' => 'App\\Services\\Bar',
        ]);
        expect($checker->extractClassNamespace($source))->toBe('Tests\\Unit');
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
