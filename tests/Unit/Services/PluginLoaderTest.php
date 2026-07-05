<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Rules\RuleRegistry;
use App\Services\PluginLoader;
use PHPUnit\Framework\TestCase;

class PluginLoaderTest extends TestCase
{
    // Specs: S005-FR-003, S005-FR-006, S005-FR-008, S010-FR-005

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/parity-plugin-loader-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_loads_rule_instances_returned_by_plugin_files(): void
    {
        $pluginPath = $this->tempDir.'/sample-rule.php';
        file_put_contents($pluginPath, <<<'PHP'
<?php

return new class implements \App\Rules\RuleInterface {
    public function name(): string { return 'sample-plugin'; }
    public function parameters(): array { return []; }
    public function evaluate(\App\Rules\RuleContext $context, array $params): \App\Rules\RuleResult
    {
        return \App\Rules\RuleResult::pass();
    }
    public function columnHeader(): ?string { return 'Sample'; }
    public function formatCell(\App\Rules\RuleResult $result): string { return 'ok'; }
    public function isEnforced(): bool { return true; }
};
PHP);

        $registry = new RuleRegistry;
        (new PluginLoader)->loadDirectory($registry, $this->tempDir);

        expect($registry->has('sample-plugin'))->toBeTrue();
    }

    public function test_loads_arrays_of_rule_instances(): void
    {
        $pluginPath = $this->tempDir.'/array-rules.php';
        file_put_contents($pluginPath, <<<'PHP'
<?php

return [
    new class implements \App\Rules\RuleInterface {
        public function name(): string { return 'first-plugin'; }
        public function parameters(): array { return []; }
        public function evaluate(\App\Rules\RuleContext $context, array $params): \App\Rules\RuleResult
        {
            return \App\Rules\RuleResult::pass();
        }
        public function columnHeader(): ?string { return 'First'; }
        public function formatCell(\App\Rules\RuleResult $result): string { return 'ok'; }
        public function isEnforced(): bool { return true; }
    },
    'not-a-rule',
];
PHP);

        $registry = new RuleRegistry;
        (new PluginLoader)->loadDirectory($registry, $this->tempDir);

        expect($registry->has('first-plugin'))->toBeTrue();
    }

    public function test_records_warnings_for_invalid_or_throwing_plugins(): void
    {
        file_put_contents($this->tempDir.'/invalid.php', '<?php return "not-a-rule";');
        file_put_contents($this->tempDir.'/throwing.php', '<?php throw new RuntimeException("boom");');

        $loader = new PluginLoader;
        $loader->loadDirectory(new RuleRegistry, $this->tempDir);

        expect($loader->getWarnings())->toHaveCount(2);
        expect(implode("\n", $loader->getWarnings()))->toContain('did not return a RuleInterface');
        expect(implode("\n", $loader->getWarnings()))->toContain('failed to load: boom');
    }

    public function test_ignores_missing_plugin_directories_and_files(): void
    {
        $loader = new PluginLoader;

        $loader->loadDirectory(new RuleRegistry, $this->tempDir.'/missing');
        $loader->loadFile(new RuleRegistry, $this->tempDir.'/missing.php');

        expect($loader->getWarnings())->toBe([]);
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
