<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class GitVersionFallbackProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('git.version', function (Application $app) {
            $versionFile = $app->basePath('VERSION');

            if (is_file($versionFile)) {
                return trim((string) file_get_contents($versionFile)) ?: 'unreleased';
            }

            return 'unreleased';
        });
    }
}
