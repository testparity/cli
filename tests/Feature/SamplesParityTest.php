<?php

// Specs: S010-FR-005, S010-FR-019, S010-AS-011

it('ships passing sample parity configurations', function (string $sample) {
    $projectRoot = dirname(__DIR__, 2);
    $config = $projectRoot.'/samples/'.$sample.'/parity.yaml';

    $this->artisan('check', [
        '--config' => $config,
        '--format' => 'json',
    ])->assertExitCode(0);
})->with([
    'php',
    'laravel',
    'vite',
    'adonisjs',
    'rust',
    'phpunit',
    'pest',
    'jest',
    'mocha',
    'vitest',
    'cargo',
]);
