<?php

// Specs: S010-FR-013, S010-FR-015, S010-FR-017, S010-AS-008, S010-EC-007, S010-EC-011

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('keeps the version file, changelog, commit, and annotated tag aligned', function () {
    $root = createReleaseFixture();

    try {
        $process = runReleaseProcess($root, ['minor']);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.2.0\n");
        expect(file_get_contents($root.'/CHANGELOG.md'))->toContain('## [1.2.0] -');

        $tag = runReleaseProcess($root, ['git', 'tag', '--list', 'v1.2.0'], false);
        expect(trim($tag->getOutput()))->toBe('v1.2.0');

        $tagType = runReleaseProcess($root, ['git', 'cat-file', '-t', 'v1.2.0'], false);
        expect(trim($tagType->getOutput()))->toBe('tag');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('rejects a duplicate release tag before mutating the repository', function () {
    $root = createReleaseFixture();

    try {
        runReleaseProcess($root, ['git', 'tag', '-a', 'v1.2.0', '-m', 'Existing release'], false);
        $headBefore = trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput());

        $process = runReleaseProcess($root, ['minor']);

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('Tag v1.2.0 already exists');
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.1.1\n");
        expect(trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput()))->toBe($headBefore);
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('rejects releases outside main before mutating the repository', function () {
    $root = createReleaseFixture();

    try {
        runReleaseProcess($root, ['git', 'switch', '-c', 'feature/release-test'], false);
        $headBefore = trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput());

        $process = runReleaseProcess($root, ['patch']);

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('Releases must be prepared from main');
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.1.1\n");
        expect(trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput()))->toBe($headBefore);
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

function createReleaseFixture(): string
{
    $root = sys_get_temp_dir().'/parity-release-'.bin2hex(random_bytes(4));
    mkdir($root.'/dev', 0777, true);

    copy(base_path('dev/release-version.sh'), $root.'/dev/release-version.sh');
    copy(base_path('CHANGELOG.md'), $root.'/CHANGELOG.md');
    file_put_contents($root.'/VERSION', "v1.1.1\n");

    runReleaseProcess($root, ['git', 'init', '--initial-branch=main'], false);
    runReleaseProcess($root, ['git', 'config', 'user.name', 'Parity Tests'], false);
    runReleaseProcess($root, ['git', 'config', 'user.email', 'parity-tests@example.invalid'], false);
    runReleaseProcess($root, ['git', 'add', '.'], false);
    runReleaseProcess($root, ['git', 'commit', '-m', 'Initial release fixture'], false);

    return $root;
}

/**
 * @param  list<string>  $arguments
 */
function runReleaseProcess(string $root, array $arguments, bool $script = true): Process
{
    $command = $script
        ? ['bash', 'dev/release-version.sh', ...$arguments]
        : $arguments;

    $process = new Process($command, $root);
    $process->setTimeout(20);
    $process->run();

    return $process;
}
