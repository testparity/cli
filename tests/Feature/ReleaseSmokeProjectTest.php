<?php

// Specs: S010-AS-010, S010-FR-018, S011-AS-001, S011-AS-002, S011-SC-001

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('builds a public install fixture that proves parity check and parity test', function () {
    $root = sys_get_temp_dir().'/parity-public-smoke-'.bin2hex(random_bytes(4));

    try {
        $build = new Process([
            'bash',
            base_path('dev/build-release-smoke-project.sh'),
            $root,
        ]);
        $build->setTimeout(20);
        $build->run();

        expect($build->isSuccessful())->toBeTrue($build->getErrorOutput());

        $check = new Process([
            PHP_BINARY,
            base_path('parity'),
            'check',
            '--config='.$root.'/parity.yaml',
            '--format=json',
        ], $root);
        $check->setTimeout(20);
        $check->run();

        expect($check->isSuccessful())->toBeTrue($check->getErrorOutput().$check->getOutput());
        expect(json_decode($check->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed'])->toBeTrue();

        $test = new Process([
            PHP_BINARY,
            base_path('parity'),
            'test',
            '--config='.$root.'/parity.yaml',
            '--format=json',
        ], $root);
        $test->setTimeout(20);
        $test->run();

        expect($test->isSuccessful())->toBeTrue($test->getErrorOutput().$test->getOutput());
        expect(json_decode($test->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed'])->toBeTrue();
        expect($root.'/.parity/per-test/index.json')->toBeFile();

        $manifest = json_decode(
            file_get_contents($root.'/.parity/per-test/index.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        expect($manifest['reports'])->toHaveCount(1);
        expect($manifest['reports'][0]['test'])->toBe('Tests\\InvoiceTotalTest');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});
