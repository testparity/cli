<?php

namespace App\Providers;

use App\Rules\EnforceCoverageLinkRule;
use App\Rules\MinimumCoverageRule;
use App\Rules\RuleRegistry;
use App\Rules\TestExistsRule;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RuleRegistry::class, function () {
            $registry = new RuleRegistry;

            // Built-in rules
            $registry->register(new TestExistsRule);
            $registry->register(new MinimumCoverageRule);
            $registry->register(new EnforceCoverageLinkRule);

            return $registry;
        });

        // Convenience bindings so rules can be resolved individually
        $this->app->bind('parity.rules.test-exists', fn () => app(RuleRegistry::class)->get('test-exists'));
        $this->app->bind('parity.rules.minimum-coverage', fn () => app(RuleRegistry::class)->get('minimum-coverage'));
        $this->app->bind('parity.rules.enforce-coverage-link', fn () => app(RuleRegistry::class)->get('enforce-coverage-link'));
    }
}
