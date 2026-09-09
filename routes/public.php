<?php

use App\Http\Controllers\PublicAnalyzerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicAnalyzerController::class, 'home'])->name('home');

Route::middleware('throttle:public-analyze')->group(function () {
    Route::post('/analyze', [PublicAnalyzerController::class, 'store'])->name('analyze.store');
});

Route::middleware('throttle:public-analysis-status')->group(function () {
    Route::get('/analysis/{public_token}', [PublicAnalyzerController::class, 'show'])
        ->where('public_token', '[0-9a-fA-F-]{36}')
        ->name('analysis.show');
    Route::get('/analysis/{public_token}/status', [PublicAnalyzerController::class, 'status'])
        ->where('public_token', '[0-9a-fA-F-]{36}')
        ->name('analysis.status');
});
