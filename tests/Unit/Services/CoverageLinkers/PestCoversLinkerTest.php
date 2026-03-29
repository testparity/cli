<?php

declare(strict_types=1);

namespace Tests\Unit\Services\CoverageLinkers;

use App\Services\CoverageLinkers\PestCoversLinker;
use PHPUnit\Framework\TestCase;

class PestCoversLinkerTest extends TestCase
{
    private PestCoversLinker $linker;

    protected function setUp(): void
    {
        $this->linker = new PestCoversLinker;
    }

    // --- name() ---

    public function test_name_is_pest_covers(): void
    {
        expect($this->linker->name())->toBe('pest-covers');
    }

    // --- supports() ---

    public function test_supports_pest_file_with_it_calls(): void
    {
        $content = <<<'PHP'
<?php
it('does something', function () {
    expect(true)->toBeTrue();
});
PHP;

        expect($this->linker->supports($content))->toBeTrue();
    }

    public function test_supports_pest_file_with_test_calls(): void
    {
        $content = <<<'PHP'
<?php
test('does something', function () {
    expect(true)->toBeTrue();
});
PHP;

        expect($this->linker->supports($content))->toBeTrue();
    }

    public function test_supports_pest_file_with_describe(): void
    {
        $content = <<<'PHP'
<?php
describe('something', function () {
    it('works', function () {});
});
PHP;

        expect($this->linker->supports($content))->toBeTrue();
    }

    public function test_supports_pest_file_with_before_each(): void
    {
        $content = <<<'PHP'
<?php
beforeEach(function () {});
it('works', function () {});
PHP;

        expect($this->linker->supports($content))->toBeTrue();
    }

    public function test_does_not_support_phpunit_class_file(): void
    {
        $content = <<<'PHP'
<?php
class FooTest extends TestCase
{
    public function test_something(): void {}
}
PHP;

        expect($this->linker->supports($content))->toBeFalse();
    }

    public function test_does_not_support_file_with_class_and_pest_calls(): void
    {
        // Has both a class declaration and it() — class declaration wins
        $content = <<<'PHP'
<?php
class FooTest extends TestCase
{
    public function test_something(): void {}
}
it('something else', function () {});
PHP;

        expect($this->linker->supports($content))->toBeFalse();
    }

    public function test_does_not_support_plain_file_with_no_pest_calls(): void
    {
        $content = <<<'PHP'
<?php
$foo = 'bar';
PHP;

        expect($this->linker->supports($content))->toBeFalse();
    }

    // --- extractCoveredClasses() ---

    public function test_extracts_single_covers_with_fully_qualified_class(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
it('works', function () {})->covers(FooService::class);
PHP;

        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_extracts_covers_class_method(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
it('works', function () {})->coversClass(FooService::class);
PHP;

        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_extracts_multiple_classes_from_single_covers_call(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
use App\Services\BarService;
it('works', function () {})->covers(FooService::class, BarService::class);
PHP;

        $useMap = [
            'FooService' => 'App\\Services\\FooService',
            'BarService' => 'App\\Services\\BarService',
        ];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe([
            'App\\Services\\FooService',
            'App\\Services\\BarService',
        ]);
    }

    public function test_extracts_from_multiple_covers_calls(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
use App\Services\BarService;
it('covers foo', function () {})->covers(FooService::class);
it('covers bar', function () {})->covers(BarService::class);
PHP;

        $useMap = [
            'FooService' => 'App\\Services\\FooService',
            'BarService' => 'App\\Services\\BarService',
        ];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe([
            'App\\Services\\FooService',
            'App\\Services\\BarService',
        ]);
    }

    public function test_deduplicates_covered_classes(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
it('covers foo twice', function () {})->covers(FooService::class, FooService::class);
PHP;

        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_returns_empty_when_no_covers_calls(): void
    {
        $source = <<<'PHP'
<?php
it('does something', function () {
    expect(true)->toBeTrue();
});
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe([]);
    }

    public function test_resolves_class_via_namespace_when_no_use_map(): void
    {
        $source = <<<'PHP'
<?php
it('works', function () {})->covers(FooService::class);
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], 'App\\Services');

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_resolves_absolute_fqcn_with_leading_backslash(): void
    {
        $source = <<<'PHP'
<?php
it('works', function () {})->covers(\App\Services\FooService::class);
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_extracts_class_from_string_literal(): void
    {
        $source = <<<'PHP'
<?php
it('works', function () {})->covers('App\Services\FooService');
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }
}
