<?php

// Specs: S003

use App\Services\GitChangedLines;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('reports staged additions, renamed lines, and deletions relative to the merge base', function () {
    $root = sys_get_temp_dir().'/parity-git-'.bin2hex(random_bytes(8));
    mkdir($root);
    $git = fn (string ...$args) => (new Process(['git', ...$args], $root))->mustRun()->getOutput();

    try {
        $git('init', '-q');
        $original = "first line\nsecond line\nthird line\nfourth line\n";
        file_put_contents($root.'/.gitattributes', "*.php -diff\n");
        file_put_contents($root.'/before.php', $original);
        file_put_contents($root.'/deleted.php', "remove\n");
        $git('add', '.');
        $git('-c', 'user.name=Parity', '-c', 'user.email=parity@example.invalid', 'commit', '-qm', 'Base');
        $base = trim($git('rev-parse', 'HEAD'));

        $git('mv', 'before.php', 'renamed with spaces.php');
        file_put_contents($root.'/renamed with spaces.php', str_replace('second line', 'new second line', $original));
        unlink($root.'/deleted.php');
        file_put_contents($root.'/added.php', "added\n");
        $git('add', '.');

        $report = (new GitChangedLines)->read($root, $base);
        expect($report['merge_base'])->toBe($base)
            ->and(array_column($report['files'], null, 'path'))->toMatchArray([
                'added.php' => ['path' => 'added.php', 'previous_path' => null, 'status' => 'A', 'lines' => [1]],
                'deleted.php' => ['path' => 'deleted.php', 'previous_path' => null, 'status' => 'D', 'lines' => []],
                'renamed with spaces.php' => ['path' => 'renamed with spaces.php', 'previous_path' => 'before.php', 'status' => 'R', 'lines' => [2]],
            ]);
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});
