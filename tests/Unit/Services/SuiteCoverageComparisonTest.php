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
