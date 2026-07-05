<?php

declare(strict_types=1);

namespace Tests\Unit\Services\CoverageLinkers;

use App\Services\CoverageLinkers\CoverageLinkerRegistry;
use PHPUnit\Framework\TestCase;

class CoverageLinkerRegistryTest extends TestCase
{
    public function test_default_registry_detects_pest_coverage_links(): void
    {
        $source = <<<'PHP'
<?php

use App\Services\FooService;

it('covers foo', function () {})->covers(FooService::class);
PHP;

        $result = (new CoverageLinkerRegistry)->extractCoveredClasses(
            $source,
            ['FooService' => 'App\\Services\\FooService'],
            null,
        );

        expect($result)->toBe([
            'linker' => 'pest-covers',
            'classes' => ['App\\Services\\FooService'],
        ]);
    }

    public function test_registry_from_config_can_limit_linkers(): void
    {
        $source = <<<'PHP'
<?php

use App\Services\FooService;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FooService::class)]
class FooServiceTest {}
PHP;

        $registry = CoverageLinkerRegistry::fromConfig(['php-attribute']);

        expect($registry->hasSupport($source))->toBeTrue();
        expect($registry->extractCoveredClasses($source, ['FooService' => 'App\\Services\\FooService'], null))->toBe([
            'linker' => 'php-attribute',
            'classes' => ['App\\Services\\FooService'],
        ]);
    }

    public function test_registry_reports_no_support_for_plain_php_file(): void
    {
        $registry = CoverageLinkerRegistry::fromConfig(['pest-covers']);
        $source = "<?php\n\nfunction helper(): void {}\n";

        expect($registry->hasSupport($source))->toBeFalse();
        expect($registry->extractCoveredClasses($source, [], null))->toBe([
            'linker' => null,
            'classes' => [],
        ]);
    }
}
