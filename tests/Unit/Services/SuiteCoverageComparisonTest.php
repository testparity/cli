<?php

use App\Services\SuiteCoverageComparison;

it('withholds the gap when isolated hits are missing from the suite', function () {
    $path = tempnam(sys_get_temp_dir(), 'parity-suite-');
    file_put_contents($path, <<<'XML'
<coverage><project><file name="src/Foo.php">
    <line num="1" type="stmt" count="1"/>
    <line num="2" type="stmt" count="0"/>
</file></project></coverage>
XML);

    try {
        $comparison = new SuiteCoverageComparison($path, sys_get_temp_dir());
        $signature = SuiteCoverageComparison::lineSignature([2, 1]);

        expect($comparison->compare('/src/Foo.php', 'src/Foo.php', 'FooTest', $signature, [1 => ['FooTest']]))
            ->toBe(['suite' => 50.0, 'associated' => 50.0, 'gap' => 0.0])
            ->and($comparison->compare('/src/Foo.php', 'src/Foo.php', 'FooTest', $signature, [2 => ['FooTest']]))
            ->toBe(['suite' => 50.0, 'associated' => null, 'gap' => null]);
    } finally {
        unlink($path);
    }
});

it('enriches native hit-only lists only when suite counts and covered identities agree', function () {
    $path = tempnam(sys_get_temp_dir(), 'parity-native-suite-');
    file_put_contents($path, '<coverage><project><file name="src/Foo.php"><line num="2" type="stmt" count="1"/><line num="3" type="stmt" count="0"/></file></project></coverage>');
    try {
        $comparison = new SuiteCoverageComparison($path, sys_get_temp_dir());
        expect($comparison->nativeExecutableLines('/src/Foo.php', 'src/Foo.php', 2, [2]))->toBe([2, 3])
            ->and($comparison->nativeExecutableLines('/src/Foo.php', 'src/Foo.php', 1, [2]))->toBeNull()
            ->and($comparison->nativeExecutableLines('/src/Foo.php', 'src/Foo.php', 2, [3]))->toBeNull()
            ->and($comparison->nativeExecutableLines('/src/Foo.php', 'src/Foo.php', 2, [4]))->toBeNull()
            ->and($comparison->nativeExecutableLines('/src/Missing.php', 'src/Missing.php', 0, []))->toBeNull();
    } finally {
        unlink($path);
    }
});
