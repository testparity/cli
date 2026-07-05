<?php

declare(strict_types=1);

// Specs: S004-FR-001, S004-FR-003, S010-FR-005

namespace Tests\Unit\Services\CoverageLinkers;

use App\Services\CoverageLinkers\PhpAttributeLinker;
use PHPUnit\Framework\TestCase;

class PhpAttributeLinkerTest extends TestCase
{
    private PhpAttributeLinker $linker;

    protected function setUp(): void
    {
        $this->linker = new PhpAttributeLinker;
    }

    // --- name() ---

    public function test_name_is_php_attribute(): void
    {
        expect($this->linker->name())->toBe('php-attribute');
    }

    // --- supports() ---

    public function test_supports_file_with_class_declaration(): void
    {
        $content = <<<'PHP'
<?php
class FooTest extends TestCase {}
PHP;

        expect($this->linker->supports($content))->toBeTrue();
    }

    public function test_does_not_support_pest_file_without_class(): void
    {
        $content = <<<'PHP'
<?php
it('works', function () {});
PHP;

        expect($this->linker->supports($content))->toBeFalse();
    }

    // --- extractCoveredClasses() ---

    public function test_extracts_single_covers_class_attribute(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FooService::class)]
class FooServiceTest extends TestCase {}
PHP;

        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_extracts_multiple_covers_class_attributes(): void
    {
        $source = <<<'PHP'
<?php
use App\Services\FooService;
use App\Services\BarService;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FooService::class)]
#[CoversClass(BarService::class)]
class FooServiceTest extends TestCase {}
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

    public function test_ignores_attribute_after_class_keyword(): void
    {
        // Attributes inside the class body should be ignored
        $source = <<<'PHP'
<?php
use App\Services\FooService;
use App\Services\BarService;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FooService::class)]
class FooServiceTest extends TestCase
{
    #[CoversClass(BarService::class)]
    public function test_something(): void {}
}
PHP;

        $useMap = [
            'FooService' => 'App\\Services\\FooService',
            'BarService' => 'App\\Services\\BarService',
        ];
        $covered = $this->linker->extractCoveredClasses($source, $useMap, null);

        // Only the pre-class attribute should be picked up
        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_returns_empty_when_no_covers_class_attribute(): void
    {
        $source = <<<'PHP'
<?php
class FooTest extends TestCase
{
    public function test_something(): void {}
}
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe([]);
    }

    public function test_resolves_via_namespace_when_no_use_map(): void
    {
        $source = <<<'PHP'
<?php
#[CoversClass(FooService::class)]
class FooServiceTest extends TestCase {}
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], 'App\\Services');

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_resolves_absolute_class_reference(): void
    {
        $source = <<<'PHP'
<?php
#[CoversClass(\App\Services\FooService::class)]
class FooServiceTest extends TestCase {}
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    public function test_ignores_non_covers_class_attributes(): void
    {
        $source = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
class FooTest extends TestCase {}
PHP;

        $covered = $this->linker->extractCoveredClasses($source, [], null);

        expect($covered)->toBe([]);
    }

    public function test_works_with_custom_attribute_fqcn(): void
    {
        $linker = new PhpAttributeLinker('My\\Custom\\Covers');

        $source = <<<'PHP'
<?php
use App\Services\FooService;
#[Covers(FooService::class)]
class FooTest extends TestCase {}
PHP;

        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $covered = $linker->extractCoveredClasses($source, $useMap, null);

        expect($covered)->toBe(['App\\Services\\FooService']);
    }

    // --- resolveClassReference() (static) ---

    public function test_resolve_class_reference_with_use_map(): void
    {
        $useMap = ['FooService' => 'App\\Services\\FooService'];
        $result = PhpAttributeLinker::resolveClassReference('FooService::class', $useMap, null);

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_with_namespace(): void
    {
        $result = PhpAttributeLinker::resolveClassReference('FooService::class', [], 'App\\Services');

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_with_leading_backslash(): void
    {
        $result = PhpAttributeLinker::resolveClassReference('\\App\\Services\\FooService::class', [], null);

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_bare_without_use_map_or_namespace(): void
    {
        $result = PhpAttributeLinker::resolveClassReference('FooService::class', [], null);

        expect($result)->toBe('FooService');
    }

    public function test_resolve_class_reference_with_sub_namespace_in_ref(): void
    {
        $useMap = ['Services' => 'App\\Services'];
        $result = PhpAttributeLinker::resolveClassReference('Services\\FooService::class', $useMap, null);

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_from_single_quoted_string(): void
    {
        $result = PhpAttributeLinker::resolveClassReference("'App\\\\Services\\\\FooService'", [], null);

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_from_double_quoted_string(): void
    {
        $result = PhpAttributeLinker::resolveClassReference('"App\\\\Services\\\\FooService"', [], null);

        expect($result)->toBe('App\\Services\\FooService');
    }

    public function test_resolve_class_reference_returns_null_for_unknown_format(): void
    {
        $result = PhpAttributeLinker::resolveClassReference('42', [], null);

        expect($result)->toBeNull();
    }
}
