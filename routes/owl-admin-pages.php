<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CallsController;
use App\Http\Controllers\CompaniesController;
use App\Http\Controllers\CompanyKnowledgeController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\AnalysisSettingsController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TranscriptionSettingsController;
use App\Http\Controllers\Settings\TelegramSettingsController;
use App\Http\Controllers\Settings\UserController as SettingsUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use OwlSolutions\CustomAdminKit\Support\AdminRouteMiddleware;

/*
| Admin preset pages (v0.5).
| Loaded from routes/web.php via:
| require __DIR__.'/owl-admin-pages.php';
*/

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('telegram.webhook');

Route::middleware(AdminRouteMiddleware::stack())->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/companies', [CompaniesController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CompaniesController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}', [CompaniesController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}', [CompaniesController::class, 'update'])->name('companies.update');
    Route::patch('/companies/{company}/toggle', [CompaniesController::class, 'toggle'])->name('companies.toggle');
    Route::delete('/companies/{company}', [CompaniesController::class, 'destroy'])->name('companies.destroy');

    Route::patch('/companies/{company}/profile', [CompanyKnowledgeController::class, 'updateProfile'])->name('companies.profile.update');
    Route::post('/companies/{company}/offerings', [CompanyKnowledgeController::class, 'storeOffering'])->name('companies.offerings.store');
    Route::patch('/companies/{company}/offerings/{offering}', [CompanyKnowledgeController::class, 'updateOffering'])->name('companies.offerings.update');
    Route::delete('/companies/{company}/offerings/{offering}', [CompanyKnowledgeController::class, 'destroyOffering'])->name('companies.offerings.destroy');
    Route::post('/companies/{company}/objections', [CompanyKnowledgeController::class, 'storeObjection'])->name('companies.objections.store');
    Route::patch('/companies/{company}/objections/{objection}', [CompanyKnowledgeController::class, 'updateObjection'])->name('companies.objections.update');
    Route::delete('/companies/{company}/objections/{objection}', [CompanyKnowledgeController::class, 'destroyObjection'])->name('companies.objections.destroy');
    Route::post('/companies/{company}/scripts', [CompanyKnowledgeController::class, 'storeScript'])->name('companies.scripts.store');
    Route::patch('/companies/{company}/scripts/{script}', [CompanyKnowledgeController::class, 'updateScript'])->name('companies.scripts.update');
    Route::delete('/companies/{company}/scripts/{script}', [CompanyKnowledgeController::class, 'destroyScript'])->name('companies.scripts.destroy');
    Route::post('/companies/{company}/scorecards', [CompanyKnowledgeController::class, 'storeScorecard'])->name('companies.scorecards.store');
    Route::patch('/companies/{company}/scorecards/{scorecard}', [CompanyKnowledgeController::class, 'updateScorecard'])->name('companies.scorecards.update');
    Route::delete('/companies/{company}/scorecards/{scorecard}', [CompanyKnowledgeController::class, 'destroyScorecard'])->name('companies.scorecards.destroy');
    Route::post('/companies/{company}/scorecards/{scorecard}/criteria', [CompanyKnowledgeController::class, 'storeCriterion'])->name('companies.scorecards.criteria.store');
    Route::patch('/companies/{company}/scorecards/{scorecard}/criteria/{criterion}', [CompanyKnowledgeController::class, 'updateCriterion'])->name('companies.scorecards.criteria.update');
    Route::delete('/companies/{company}/scorecards/{scorecard}/criteria/{criterion}', [CompanyKnowledgeController::class, 'destroyCriterion'])->name('companies.scorecards.criteria.destroy');

    Route::get('/employees', [EmployeesController::class, 'index'])->name('employees.index');
    Route::get('/employees/{employee}', [EmployeesController::class, 'show'])->name('employees.show');
    Route::post('/employees', [EmployeesController::class, 'store'])->name('employees.store');
    Route::patch('/employees/{employee}', [EmployeesController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/toggle', [EmployeesController::class, 'toggle'])->name('employees.toggle');
    Route::delete('/employees/{employee}', [EmployeesController::class, 'destroy'])->name('employees.destroy');

    Route::get('/calls', [CallsController::class, 'index'])->name('calls.index');
    Route::get('/calls/create', [CallsController::class, 'create'])->name('calls.create');
    Route::post('/calls', [CallsController::class, 'store'])->name('calls.store');
    Route::get('/calls/{call}', [CallsController::class, 'show'])->name('calls.show');
    Route::patch('/calls/{call}', [CallsController::class, 'update'])->name('calls.update');
    Route::delete('/calls/{call}', [CallsController::class, 'destroy'])->name('calls.destroy');
    Route::get('/calls/{call}/audio', [CallsController::class, 'audio'])->name('calls.audio');
    Route::get('/calls/{call}/download', [CallsController::class, 'download'])->name('calls.download');
    Route::post('/calls/{call}/transcribe', [CallsController::class, 'transcribe'])->name('calls.transcribe');
    Route::post('/calls/{call}/analyze', [CallsController::class, 'analyze'])->name('calls.analyze');

    Route::get('/customers', [CustomersController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomersController::class, 'store'])->name('customers.store');
    Route::patch('/customers/{customer}', [CustomersController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomersController::class, 'destroy'])->name('customers.destroy');

    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::patch('/orders/{order}', [OrdersController::class, 'update'])->name('orders.update');
    Route::delete('/orders/{order}', [OrdersController::class, 'destroy'])->name('orders.destroy');
    Route::patch('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.status');
    Route::patch('/orders/{order}/assign', [OrdersController::class, 'assign'])->name('orders.assign');

    Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
    Route::post('/services', [ServicesController::class, 'store'])->name('services.store');
    Route::patch('/services/{service}', [ServicesController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServicesController::class, 'destroy'])->name('services.destroy');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::patch('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/language', [SettingsController::class, 'updateLanguage'])->name('settings.language.update');
    Route::post('/settings/users', [SettingsUserController::class, 'store'])->name('settings.users.store');
    Route::patch('/settings/users/{user}', [SettingsUserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [SettingsUserController::class, 'destroy'])->name('settings.users.destroy');

    Route::post('/settings/telegram/save-token', [TelegramSettingsController::class, 'saveToken'])
        ->name('settings.telegram.save-token');
    Route::post('/settings/telegram/check', [TelegramSettingsController::class, 'check'])
        ->name('settings.telegram.check');
    Route::post('/settings/telegram/set-webhook', [TelegramSettingsController::class, 'setWebhook'])
        ->name('settings.telegram.set-webhook');
    Route::post('/settings/telegram/remove-webhook', [TelegramSettingsController::class, 'removeWebhook'])
        ->name('settings.telegram.remove-webhook');

    Route::get('/app-settings', function () {
        return redirect()->route('settings.index', ['tab' => 'app']);
    })->name('app-settings.index');

    Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
    Route::post('/ai-settings/{provider}/key', [AiSettingsController::class, 'saveKey'])->name('ai-settings.save-key');
    Route::post('/ai-settings/{provider}/check', [AiSettingsController::class, 'check'])->name('ai-settings.check');
    Route::post('/ai-settings/{provider}/activate', [AiSettingsController::class, 'activate'])->name('ai-settings.activate');
    Route::post('/ai-settings/deactivate', [AiSettingsController::class, 'deactivate'])->name('ai-settings.deactivate');

    Route::post('/settings/transcription', [TranscriptionSettingsController::class, 'save'])->name('settings.transcription.save');
    Route::post('/settings/transcription/check', [TranscriptionSettingsController::class, 'check'])->name('settings.transcription.check');
    Route::patch('/settings/analysis', [AnalysisSettingsController::class, 'update'])->name('settings.analysis.update');

    Route::get('/statistics/logs', function () {
        return Inertia::render('Statistics/Logs');
    })->name('statistics.logs');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
