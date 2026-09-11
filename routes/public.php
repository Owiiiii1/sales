<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicAnalyzerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicAnalyzerController::class, 'home'])->name('home');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

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
    Route::post('/analysis/{public_token}/cancel', [PublicAnalyzerController::class, 'cancel'])
        ->where('public_token', '[0-9a-fA-F-]{36}')
        ->name('analysis.cancel');
});
