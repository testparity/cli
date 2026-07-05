<?php

namespace App\Providers;

use App\Rules\CoverageAttributionRule;
use App\Rules\EnforceCoverageLinkRule;
use App\Rules\MatchedCoverageRule;
use App\Rules\MinimumCoverageRule;
use App\Rules\RuleRegistry;
use App\Rules\TestExistsRule;
use Illuminate\Support\ServiceProvider;

/**
 * Specs: S002
 */
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
            $registry->register(new MatchedCoverageRule);
            $registry->register(new CoverageAttributionRule);

            return $registry;
        });

        // Convenience bindings so rules can be resolved individually
        foreach (['test-exists', 'minimum-coverage', 'enforce-coverage-link', 'matched-coverage', 'coverage-attribution'] as $name) {
            $this->app->bind("parity.rules.{$name}", fn () => app(RuleRegistry::class)->get($name));
        }
    }
}
