<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $recentCalls = Call::query()
            ->with(['company:id,name', 'employee:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(static fn (Call $call): array => [
                'id' => $call->id,
                'company_name' => $call->company?->name,
                'employee_name' => $call->employee?->full_name,
                'status' => $call->status,
                'duration_seconds' => $call->duration_seconds,
                'recorded_at' => optional($call->recorded_at)->toIso8601String(),
                'created_at' => optional($call->created_at)->toIso8601String(),
                'show_url' => route('calls.show', $call),
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'stats' => [
                'companies' => Company::query()->count(),
                'active_employees' => Employee::query()->where('is_active', true)->count(),
                'total_calls' => Call::query()->count(),
                'calls_completed' => Call::query()->where('status', 'completed')->count(),
                'calls_processing' => Call::query()->where('status', 'processing')->count(),
                'calls_failed' => Call::query()->where('status', 'failed')->count(),
            ],
            'recentCalls' => $recentCalls,
        ]);
    }
}
