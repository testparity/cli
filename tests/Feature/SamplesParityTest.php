<?php

// Specs: S010-FR-005, S010-FR-019, S010-AS-011

use Symfony\Component\Process\Process;

it('ships passing sample parity configurations', function (string $sample) {
    $projectRoot = dirname(__DIR__, 2);
    $config = $projectRoot.'/samples/'.$sample.'/parity.yaml';

    $process = new Process([
        PHP_BINARY,
        $projectRoot.'/parity',
        'check',
        '--config='.$config,
        '--format=json',
    ], $projectRoot);
    $process->run();

    expect($process->getExitCode(), $process->getOutput().$process->getErrorOutput())->toBe(0);
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
