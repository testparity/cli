<?php

// Specs: S010-FR-013, S010-FR-015, S010-FR-017, S010-AS-008, S010-EC-005, S010-EC-007, S010-EC-011

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('keeps the version file, changelog, commit, and annotated tag aligned', function () {
    $root = createReleaseFixture();

    try {
        $process = runReleaseProcess($root, ['minor']);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.2.0\n");
        expect(file_get_contents($root.'/CHANGELOG.md'))
            ->toContain("## [Unreleased]\n\n## [1.2.0] -")
            ->toContain('`parity test` execution mode')
            ->not->toContain('- (new release)');

        $tag = runReleaseProcess($root, ['git', 'tag', '--list', 'v1.2.0'], false);
        expect(trim($tag->getOutput()))->toBe('v1.2.0');

        $tagType = runReleaseProcess($root, ['git', 'cat-file', '-t', 'v1.2.0'], false);
        expect(trim($tagType->getOutput()))->toBe('tag');

        $metadata = runReleaseProcess($root, ['bash', 'dev/verify-release-metadata.sh', 'v1.2.0'], false);
        expect($metadata->isSuccessful())->toBeTrue($metadata->getErrorOutput());
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('rejects a release without curated unreleased notes before mutating the repository', function () {
    $root = createReleaseFixture();

    try {
        $changelog = file_get_contents($root.'/CHANGELOG.md');
        $changelog = preg_replace(
            '/## \[Unreleased\].*?(?=\n## \[1\.1\.1\])/s',
            "## [Unreleased]\n",
            $changelog,
        );
        file_put_contents($root.'/CHANGELOG.md', $changelog);
        runReleaseProcess($root, ['git', 'add', 'CHANGELOG.md'], false);
        runReleaseProcess($root, ['git', 'commit', '-m', 'Remove unreleased notes'], false);
        $headBefore = trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput());

        $process = runReleaseProcess($root, ['minor']);

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('Add release notes beneath ## [Unreleased]');
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.1.1\n");
        expect(trim(runReleaseProcess($root, ['git', 'rev-parse', 'HEAD'], false)->getOutput()))->toBe($headBefore);
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('defaults to a non-mutating patch preview when only dry run is supplied', function () {
    $root = createReleaseFixture();

    try {
        $process = runReleaseProcess($root, ['--dry-run']);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        expect($process->getOutput())
            ->toContain('New version: v1.1.2')
            ->toContain('[DRY RUN] Would update VERSION: v1.1.1 -> v1.1.2');
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.1.1\n");

        $tag = runReleaseProcess($root, ['git', 'tag', '--list', 'v1.1.2'], false);
        expect(trim($tag->getOutput()))->toBe('');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('rejects tracked or untracked changes before mutating release metadata', function () {
    $root = createReleaseFixture();

    try {
        file_put_contents($root.'/release-notes.tmp', "untracked\n");

        $process = runReleaseProcess($root, ['minor']);

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('Working tree is dirty');
        expect(file_get_contents($root.'/VERSION'))->toBe("v1.1.1\n");
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('rejects release metadata when the tracked version differs from the tag', function () {
    $root = createReleaseFixture();

    try {
        $process = runReleaseProcess(
            $root,
            ['bash', 'dev/verify-release-metadata.sh', 'v1.2.0'],
            false,
        );

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('VERSION contains v1.1.1 but the release tag is v1.2.0');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('pushes the release commit and annotated tag to the remote together', function () {
    $root = createReleaseFixture();
    $remote = sys_get_temp_dir().'/parity-release-remote-'.bin2hex(random_bytes(4));

    try {
        runReleaseProcess($root, ['git', 'init', '--bare', $remote], false);
        runReleaseProcess($root, ['git', 'remote', 'add', 'origin', $remote], false);
        runReleaseProcess($root, ['git', 'push', '-u', 'origin', 'main'], false);

        $process = runReleaseProcess($root, ['minor', '--push']);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        $remoteMain = runReleaseProcess(
            $root,
            ['git', '--git-dir='.$remote, 'rev-parse', 'refs/heads/main'],
            false,
        );
        $remoteTag = runReleaseProcess(
            $root,
            ['git', '--git-dir='.$remote, 'rev-parse', 'refs/tags/v1.2.0^{}'],
            false,
        );

        expect(trim($remoteMain->getOutput()))->toBe(trim($remoteTag->getOutput()));
    } finally {
        (new Filesystem)->deleteDirectory($root);
        (new Filesystem)->deleteDirectory($remote);
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
    copy(base_path('dev/verify-release-metadata.sh'), $root.'/dev/verify-release-metadata.sh');
    file_put_contents($root.'/CHANGELOG.md', <<<'MARKDOWN'
# Changelog

## [Unreleased]

### Added
- `parity test` execution mode.

## [1.1.1] - 2026-07-06

### Changed
- Previous release fixture.
MARKDOWN
        ."\n");
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
