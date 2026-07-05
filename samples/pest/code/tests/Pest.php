<?php

declare(strict_types=1);

uses()->beforeEach(function (): void {
    spl_autoload_register(function (string $class): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }

        $path = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
})->in(__DIR__);
