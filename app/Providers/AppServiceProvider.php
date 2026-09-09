<?php

namespace App\Providers;

use App\Support\TestingDatabaseGuard;
use App\Services\Analysis\ConfiguredSalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Transcription\ElevenLabsTranscriptionClient;
use App\Services\Transcription\TranscriptionProvider;
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
        $this->app->bind(TranscriptionProvider::class, ElevenLabsTranscriptionClient::class);
        $this->app->bind(SalesAnalysisProvider::class, ConfiguredSalesAnalysisProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            TestingDatabaseGuard::enforceFromApplication($this->app);
        }

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
