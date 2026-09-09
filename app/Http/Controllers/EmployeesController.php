<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->query('company_id');

        $employees = Employee::query()
            ->with('company:id,name')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'company_name' => $employee->company?->name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'position' => $employee->position,
                'external_id' => $employee->external_id,
                'is_active' => $employee->is_active,
                'created_at' => optional($employee->created_at)->toIso8601String(),
            ])
            ->all();

        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->all();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'companies' => $companies,
            'filters' => [
                'company_id' => $companyId !== null && $companyId !== '' ? (int) $companyId : null,
            ],
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        Employee::query()->create($data);

        return redirect()->route('employees.index');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index');
    }

    public function toggle(Employee $employee): RedirectResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        return redirect()->route('employees.index');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->calls()->exists()) {
            return redirect()
                ->route('employees.index')
                ->withErrors([
                    'employee' => 'This employee still has calls and cannot be deleted.',
                ]);
        }

        $employee->delete();

        return redirect()->route('employees.index');
    }
}
