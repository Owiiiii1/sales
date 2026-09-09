<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompaniesController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()
            ->withCount(['employees', 'calls'])
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'website' => $company->website,
                'industry' => $company->industry,
                'description' => $company->description,
                'country' => $company->country,
                'city' => $company->city,
                'phone' => $company->phone,
                'email' => $company->email,
                'is_active' => $company->is_active,
                'employees_count' => $company->employees_count,
                'calls_count' => $company->calls_count,
                'created_at' => optional($company->created_at)->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        Company::query()->create($data);

        return redirect()->route('companies.index');
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('companies.index');
    }

    public function toggle(Company $company): RedirectResponse
    {
        $company->update(['is_active' => ! $company->is_active]);

        return redirect()->route('companies.index');
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->employees()->exists() || $company->calls()->exists()) {
            return redirect()
                ->route('companies.index')
                ->withErrors([
                    'company' => 'This company still has employees or calls and cannot be deleted.',
                ]);
        }

        $company->delete();

        return redirect()->route('companies.index');
    }
}
