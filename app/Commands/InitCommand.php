<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class InitCommand extends Command
{
    protected $signature = 'init';

    protected $description = 'Create a default parity.yaml in the current directory';

    private const DEFAULT_CONFIG = <<<'YAML'
# Parity — structural parity and coverage validation
# Docs: https://github.com/eupry/parity

# Project-wide settings (all configurable, sensible defaults for PHP/Laravel)
settings:
  # PSR-4 style namespace roots: directory → namespace prefix
  namespace_roots:
    app: App
    tests: Tests
  # File extensions and test naming
  source_extension: ".php"
  test_suffix: "Test"       # FooService.php → FooServiceTest.php
  test_extension: ".php"
  # Namespace separator (\ for PHP, . for Java/Python, / for Go)
  namespace_separator: "\\"

# Coverage file(s): first existing path is used
# Supports Clover XML (single file) or PHPUnit XML (directory with index.xml)
coverage_xml: [coverage-xml, clover.xml]

# Global coverage thresholds
min_coverage: 80
# min_coverage_global: 80

# Structure definitions with pluggable rules
structure:
  - name: "Unit Actions"
    paths:
      source: "app/Actions"
      test: "tests/Unit/Actions"
    rules:
      - enforce-coverage-link
      - minimum-coverage:
          min: 90
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
