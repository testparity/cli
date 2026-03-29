<?php

declare(strict_types=1);

namespace App\Services;

use App\Rules\RuleInterface;
use App\Rules\RuleRegistry;

/**
 * Discovers and loads parity plugins from multiple sources:
 * project-local (.parity/plugins/), global user (~/.parity/plugins/),
 * and composer packages (extra.parity.rules in composer.json).
 *
 * Plugin PHP files must return a RuleInterface instance or an array of them.
 */
class PluginLoader
{
    private array $warnings = [];

    /**
     * Load all plugins from all sources into the registry.
     */
    public function loadAll(RuleRegistry $registry, string $projectRoot): void
    {
        // 1. Project-local plugins
        $this->loadDirectory($registry, $projectRoot . '/.parity/plugins');

        // 2. Global user plugins
        $home = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME');
        if ($home !== false && $home !== '') {
            $this->loadDirectory($registry, $home . '/.parity/plugins');
        }

        // 3. Composer packages declaring parity rules
        $this->loadComposerPlugins($registry, $projectRoot);
    }

    /**
     * Load all *.php files from a directory.
     * Each file must return a RuleInterface or an array of RuleInterface.
     */
    public function loadDirectory(RuleRegistry $registry, string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');
        if ($files === false) {
            return;
        }

        sort($files);
        foreach ($files as $file) {
            $this->loadFile($registry, $file);
        }
    }

    /**
     * Load a single plugin file.
     */
    public function loadFile(RuleRegistry $registry, string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        try {
            $result = require $file;

            if ($result instanceof RuleInterface) {
                $registry->register($result);

                return;
            }

            if (is_array($result)) {
                foreach ($result as $rule) {
                    if ($rule instanceof RuleInterface) {
                        $registry->register($rule);
                    }
                }

                return;
            }

            $this->warnings[] = "Plugin {$file} did not return a RuleInterface or array of rules";
        } catch (\Throwable $e) {
            $this->warnings[] = "Plugin {$file} failed to load: {$e->getMessage()}";
        }
    }

    /**
     * Scan installed composer packages for parity rule declarations.
     *
     * Looks for packages with extra.parity.rules in their composer.json,
     * which should be an array of fully qualified class names implementing RuleInterface.
     */
    public function loadComposerPlugins(RuleRegistry $registry, string $projectRoot): void
    {
        $installedPath = $projectRoot . '/vendor/composer/installed.json';
        if (! is_file($installedPath)) {
            return;
        }

        $installed = @json_decode(file_get_contents($installedPath), true);
        if (! is_array($installed)) {
            return;
        }

        // Composer v2 wraps packages in a "packages" key
        $packages = $installed['packages'] ?? $installed;
        if (! is_array($packages)) {
            return;
        }

        // Ensure project autoloader is loaded
        $autoload = $projectRoot . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        foreach ($packages as $package) {
            $ruleClasses = $package['extra']['parity']['rules'] ?? null;
            if (! is_array($ruleClasses)) {
                continue;
            }

            $packageName = $package['name'] ?? 'unknown';
            foreach ($ruleClasses as $className) {
                if (! is_string($className) || ! class_exists($className)) {
                    $this->warnings[] = "Composer plugin {$packageName}: class {$className} not found";
                    continue;
                }

                try {
                    $rule = new $className;
                    if ($rule instanceof RuleInterface) {
                        $registry->register($rule);
                    } else {
                        $this->warnings[] = "Composer plugin {$packageName}: {$className} does not implement RuleInterface";
                    }
                } catch (\Throwable $e) {
                    $this->warnings[] = "Composer plugin {$packageName}: {$className} failed to instantiate: {$e->getMessage()}";
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
