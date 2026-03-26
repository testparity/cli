<?php

it('creates parity.yaml when it does not exist', function () {
    $cwd = getcwd();
    $configPath = $cwd . '/parity.yaml';

    $hadConfig = is_file($configPath);
    if ($hadConfig) {
        $backup = $configPath . '.bak';
        rename($configPath, $backup);
    }

    try {
        $this->artisan('init')->assertExitCode(0);
        expect(is_file($configPath))->toBeTrue();
        expect(file_get_contents($configPath))->toContain('structure:');
    } finally {
        if (is_file($configPath)) {
            unlink($configPath);
        }
        if ($hadConfig && isset($backup) && is_file($backup)) {
            rename($backup, $configPath);
        }
    }
});

it('does not overwrite existing parity.yaml', function () {
    $cwd = getcwd();
    $configPath = $cwd . '/parity.yaml';

    $hadConfig = is_file($configPath);
    $originalContent = $hadConfig ? file_get_contents($configPath) : null;
    if (! $hadConfig) {
        file_put_contents($configPath, 'existing');
    }

    try {
        $this->artisan('init')->assertExitCode(0);
        expect(file_get_contents($configPath))->toBe($hadConfig ? $originalContent : 'existing');
    } finally {
        if (! $hadConfig && is_file($configPath)) {
            unlink($configPath);
        }
    }
});
