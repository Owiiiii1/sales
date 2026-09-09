<?php

namespace App\Http\Controllers;

use App\Http\Requests\CallRequest;
use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CallsController extends Controller
{
    public function index(): Response
    {
        $calls = Call::query()
            ->with(['company:id,name', 'employee:id,first_name,last_name,company_id'])
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Call $call): array => [
                'id' => $call->id,
                'company_id' => $call->company_id,
                'company_name' => $call->company?->name,
                'employee_id' => $call->employee_id,
                'employee_name' => $call->employee?->full_name,
                'source' => $call->source,
                'original_filename' => $call->original_filename,
                'status' => $call->status,
                'duration_seconds' => $call->duration_seconds,
                'recorded_at' => optional($call->recorded_at)->toIso8601String(),
                'created_at' => optional($call->created_at)->toIso8601String(),
            ])
            ->all();

        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $employees = Employee::query()
            ->orderBy('first_name')
            ->get(['id', 'company_id', 'first_name', 'last_name'])
            ->map(static fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'full_name' => $employee->full_name,
            ])
            ->all();

        return Inertia::render('Calls/Index', [
            'calls' => $calls,
            'companies' => $companies,
            'employees' => $employees,
            'statuses' => Call::STATUSES,
        ]);
    }

    public function store(CallRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['uploaded_by'] = $request->user()?->id;
        $data['source'] = $data['source'] ?? 'manual';

        Call::query()->create($data);

        return redirect()->route('calls.index');
    }

    public function update(CallRequest $request, Call $call): RedirectResponse
    {
        $call->update($request->validated());

        return redirect()->route('calls.index');
    }

    public function destroy(Call $call): RedirectResponse
    {
        $call->delete();

        return redirect()->route('calls.index');
    }
}
