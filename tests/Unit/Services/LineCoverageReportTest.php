<?php

use App\Services\LineCoverageReport;
use App\Services\SuiteCoverageComparison;

it('exports exact associated lines and hashes physical source lines without source text', function () {
    $path = tempnam(sys_get_temp_dir(), 'parity-lines-');
    file_put_contents($path, "<?php\r\nreturn 1;\r\nreturn 2;\r\n");
    try {
        $data = [
            'attributionComplete' => true,
            'executableLines' => ['src/Foo.php' => [2, 3]],
            'totalExecutable' => ['src/Foo.php' => 2],
            'lineCoverage' => ['src/Foo.php' => [2 => ['P\\Tests\\FooTest::adds'], 3 => ['Tests\\OtherFooTest::shared']]],
        ];
        $result = (new LineCoverageReport)->build($path, $path, 'src/Foo.php', 'Tests\\FooTest', true, $data);
        expect($result['complete'])->toBeTrue()
            ->and($result['executableLines'])->toBe([2, 3])
            ->and($result['coveredLines'])->toBe([2])
            ->and($result['sourceHash'])->toBe(hash_file('sha256', $path))
            ->and($result['lineHashes'])->toBe(array_map(fn ($line) => hash('sha256', $line), ['<?php', 'return 1;', 'return 2;']))
            ->and(json_encode($result))->not->toContain('return 1;');
        $missing = (new LineCoverageReport)->build($path, $path, 'src/Foo.php', 'Tests\\FooTest', false, $data);
        expect($missing['complete'])->toBeTrue()->and($missing['coveredLines'])->toBe([]);
        $data['reportedTests'] = ['Tests\\FooTest'];
        $data['lineSignaturesByTest'] = ['Tests\\FooTest' => ['src/Foo.php' => SuiteCoverageComparison::lineSignature([2, 4])]];
        expect((new LineCoverageReport)->build($path, $path, 'src/Foo.php', 'Tests\\FooTest', true, $data)['complete'])->toBeFalse();
        $data['lineSignaturesByTest']['Tests\\FooTest']['src/Foo.php'] = SuiteCoverageComparison::lineSignature([2, 3]);
        expect((new LineCoverageReport)->build($path, $path, 'src/Foo.php', 'Tests\\FooTest', true, $data)['complete'])->toBeTrue();
    } finally {
        unlink($path);
    }
});

it('distinguishes unavailable evidence from complete zero executable coverage', function () {
    $path = tempnam(sys_get_temp_dir(), 'parity-lines-');
    try {
        $reporter = new LineCoverageReport;
        expect($reporter->build($path, $path, 'empty.php', 'Tests\\EmptyTest', false, [])['complete'])->toBeFalse();
        $data = ['attributionComplete' => true, 'executableLines' => ['empty.php' => []], 'totalExecutable' => ['empty.php' => 0]];
        $report = $reporter->build($path, $path, 'empty.php', 'Tests\\EmptyTest', false, $data);
        expect($report['complete'])->toBeTrue()->and($report['executableLines'])->toBe([])->and($report['coveredLines'])->toBe([])->and($report['lineHashes'])->toBe([]);
        $data['executableLines']['empty.php'] = [1];
        $data['totalExecutable']['empty.php'] = 1;
        expect($reporter->build($path, $path, 'empty.php', 'Tests\\EmptyTest', false, $data)['complete'])->toBeFalse();
    } finally {
        unlink($path);
    }
});

it('rejects duplicate or out of range executable identities', function (array $executable) {
    $path = tempnam(sys_get_temp_dir(), 'parity-lines-');
    file_put_contents($path, "one\ntwo\n");
    try {
        $data = ['attributionComplete' => true, 'executableLines' => ['source' => $executable], 'totalExecutable' => ['source' => count($executable)]];
        expect((new LineCoverageReport)->build($path, $path, 'source', 'Test', false, $data)['complete'])->toBeFalse();
    } finally {
        unlink($path);
    }
})->with([[[1, 1]], [[3]], [[0]]]);

it('rejects coerced or absent coverage proof in per-test artifacts', function (array $override) {
    $dir = sys_get_temp_dir().'/parity-proof-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $file = array_replace(['path' => 'src/Foo.php', 'totalExecutableLines' => 1, 'executableLines' => [2], 'coveredLines' => [2]], $override);
    if ($file['coveredLines'] === null) {
        unset($file['coveredLines']);
    }
    file_put_contents($dir.'/index.json', json_encode(['version' => 1, 'kind' => 'parity-per-test-coverage', 'reports' => [['test' => 'Tests\\FooTest', 'path' => 'report.json']]]));
    file_put_contents($dir.'/report.json', json_encode(['version' => 1, 'test' => 'Tests\\FooTest', 'files' => [$file]]));
    try {
        $result = (new App\Services\ParityPerTestCoverageReader)->read($dir, null, true);
        expect($result['executableLines'] ?? [])->not->toHaveKey('src/Foo.php');
    } finally {
        unlink($dir.'/index.json');
        unlink($dir.'/report.json');
        rmdir($dir);
    }
})->with([
    [['coveredLines' => ['2foo']]],
    [['coveredLines' => null]],
    [['coveredLines' => [2, 2]]],
    [['totalExecutableLines' => 1.5]],
]);

it('uses supplied suite lines to complete native evidence and retains associated attribution', function () {
    $source = tempnam(sys_get_temp_dir(), 'parity-native-source-');
    $clover = tempnam(sys_get_temp_dir(), 'parity-native-clover-');
    file_put_contents($source, "<?php\nreturn 1;\nreturn 2;\n");
    file_put_contents($clover, '<coverage><file name="src/Foo.php"><line num="2" count="1"/><line num="3" count="0"/></file></coverage>');
    try {
        $data = [
            'attributionComplete' => true,
            'observedLines' => ['src/Foo.php' => [2]],
            'totalExecutable' => ['src/Foo.php' => 2],
            'lineCoverage' => ['src/Foo.php' => [2 => ['Tests\\FooTest::one']]],
        ];
        $suite = new SuiteCoverageComparison($clover, sys_get_temp_dir());
        $report = (new LineCoverageReport)->build($source, $source, 'src/Foo.php', 'Tests\\FooTest', true, $data, $suite);
        expect($report['complete'])->toBeTrue()->and($report['executableLines'])->toBe([2, 3])->and($report['coveredLines'])->toBe([2]);
        $data['observedLines']['src/Foo.php'] = [3];
        expect((new LineCoverageReport)->build($source, $source, 'src/Foo.php', 'Tests\\FooTest', true, $data, $suite)['complete'])->toBeFalse();
    } finally {
        unlink($source);
        unlink($clover);
    }
});
