<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class InitCommand extends Command
{
    protected $signature = 'init';

    protected $description = 'Create a default parity.yaml in the current directory';

    private const DEFAULT_CONFIG = <<<'YAML'
# Coverage file(s): string or array; first existing file is used (Clover XML only)
coverage_xml: [clover.xml, coverage.xml]
# Per-file coverage minimum (each source file must meet this %)
min_coverage: 80
# Optional: overall project coverage minimum
# min_coverage_global: 80

structure:
  - name: "Unit Actions"
    source_path: "app/Actions"
    test_path: "tests/Unit/Actions"
    enforce_attribute: 'PHPUnit\Framework\Attributes\CoversClass'
    # Optional: override per-file minimum for this structure
    # min_coverage: 90
YAML;

    public function handle(): int
    {
        $cwd = getcwd();
        if ($cwd === false) {
            $this->error('Could not determine current directory.');

            return self::FAILURE;
        }

        $configPath = $cwd . '/parity.yaml';

        if (is_file($configPath)) {
            $this->warn('parity.yaml already exists.');

            return self::SUCCESS;
        }

        if (file_put_contents($configPath, self::DEFAULT_CONFIG) === false) {
            $this->error('Could not write parity.yaml.');

            return self::FAILURE;
        }

        $this->info('Created parity.yaml.');

        return self::SUCCESS;
    }
}
