<?php

use App\Slugger;

it('creates URL slugs')
    ->covers(Slugger::class)
    ->expect(fn () => (new Slugger)->slug('Parity Checks Everything'))
    ->toBe('parity-checks-everything');
