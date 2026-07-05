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
    $process->setEnv([
        ...$_ENV,
        'XDEBUG_MODE' => 'off',
    ]);
    $process->run();

    $this->assertSame(
        0,
        $process->getExitCode(),
        "Sample [{$sample}] failed.\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}"
    );
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
