<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-analyze', function (Request $request) {
            return Limit::perMinute((int) config('sales-analyzer.public_upload_per_minute', 10))
                ->by($request->ip());
        });

        RateLimiter::for('public-analysis-status', function (Request $request) {
            return Limit::perMinute((int) config('sales-analyzer.public_status_per_minute', 60))
                ->by($request->ip());
        });
    }
}
