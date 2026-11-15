<?php

use App\Services\PhpInterfaceDeclarations;

it('identifies PHP files containing only interface declarations', function (string $source, bool $expected) {
    $path = tempnam(sys_get_temp_dir(), 'parity-interface-');
    file_put_contents($path, $source);

    try {
        expect(PhpInterfaceDeclarations::isOnlyInterfaces($path))->toBe($expected);
    } finally {
        unlink($path);
    }
})->with([
    'global' => ['<?php interface Client { public function request(): string; }', true],
    'namespace and import' => [<<<'PHP_SOURCE'
        <?php
        declare(strict_types=1);

        namespace App\Contracts;

        use App\Types\{Request, Response};

        #[\Attribute(options: ['enabled' => true])]
        interface Client extends Readable
        {
            public function request(Request $request): Response;
        }

        interface Readable {}
        PHP_SOURCE, true],
    'bracketed namespaces' => ['<?php namespace App { interface Client {} } namespace Other { interface Client {} }', true],
    'implementation in the same file' => ['<?php interface Client {} class RealClient implements Client {}', false],
    'function in the same file' => ['<?php interface Client {} function createClient() {}', false],
    'code in the same file' => ['<?php interface Client {} echo "loaded";', false],
    'block declare' => ['<?php interface Client {} declare(ticks=1) { echo 1; }', false],
    'short echo tag' => ['<?php interface Client {} ?><?= "loaded" ?>', false],
    'inline HTML' => ['<?php interface Client {} ?>loaded', false],
    'interface name in a comment' => ['<?php // interface Client {}', false],
    'invalid PHP' => ['<?php interface Client {', false],
]);

it('keeps a missing file eligible for checking', function () {
    $path = sys_get_temp_dir().'/parity-missing-'.bin2hex(random_bytes(8));

    expect(PhpInterfaceDeclarations::isOnlyInterfaces($path))->toBeFalse();
});
