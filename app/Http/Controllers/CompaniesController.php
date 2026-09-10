<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\CompanyAnalyticsService;
use App\Support\CompanyKnowledgeCompleteness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(
        Request $request,
        Company $company,
        CompanyKnowledgeCompleteness $completeness,
        CompanyAnalyticsService $analytics,
    ): Response {
        $tab = $request->query('tab', 'overview');
        $allowed = ['overview', 'analytics', 'knowledge', 'offerings', 'objections', 'scripts', 'scorecard', 'employees', 'calls'];
        if (! in_array($tab, $allowed, true)) {
            $tab = 'overview';
        }

        $company->load([
            'profile',
            'offerings' => fn ($query) => $query->orderBy('name'),
            'objections' => fn ($query) => $query->orderBy('priority')->orderBy('id'),
            'salesScripts' => fn ($query) => $query->orderBy('name'),
            'scorecards.criteria',
            'employees' => fn ($query) => $query->orderBy('first_name'),
        ]);

        $calls = $company->calls()
            ->with('employee:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($call): array => [
                'id' => $call->id,
                'status' => $call->status,
                'original_filename' => $call->original_filename,
                'employee_name' => $call->employee?->full_name,
                'created_at' => optional($call->created_at)->toIso8601String(),
                'show_url' => route('calls.show', $call),
            ])
            ->all();

        $filter = $tab === 'analytics'
            ? AnalyticsFilter::fromRequest($request, $company->id)
            : null;

        $scorecards = $company->scorecards->map(function ($scorecard): array {
            $weight = (float) $scorecard->criteria->where('is_active', true)->sum('weight');

            return [
                'id' => $scorecard->id,
                'name' => $scorecard->name,
                'description' => $scorecard->description,
                'is_default' => $scorecard->is_default,
                'is_active' => $scorecard->is_active,
                'total_weight' => $weight,
                'weight_warning' => abs($weight - 100) > 0.01,
                'criteria' => $scorecard->criteria->map(fn ($criterion): array => [
                    'id' => $criterion->id,
                    'key' => $criterion->key,
                    'name' => $criterion->name,
                    'description' => $criterion->description,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'is_critical' => $criterion->is_critical,
                    'ai_instructions' => $criterion->ai_instructions,
                    'sequence' => $criterion->sequence,
                    'is_active' => $criterion->is_active,
                ])->all(),
            ];
        })->all();

        return Inertia::render('Companies/Show', [
            'tab' => $tab,
            'company' => [
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
            ],
            'profile' => $company->profile?->only([
                'short_description',
                'sales_context',
                'target_audience',
                'ideal_customer_profile',
                'value_proposition',
                'usp',
                'pricing_context',
                'competitors',
                'customer_pains',
                'sales_goals',
                'desired_next_steps',
                'forbidden_claims',
                'mandatory_questions',
                'notes',
            ]) ?? [],
            'completeness' => $completeness->for($company),
            'offerings' => $company->offerings,
            'objections' => $company->objections,
            'scripts' => $company->salesScripts,
            'scorecards' => $scorecards,
            'employees' => $company->employees->map(fn ($employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'position' => $employee->position,
                'is_active' => $employee->is_active,
            ])->all(),
            'calls' => $calls,
            'filters' => $filter?->toArray(),
            'analytics' => $filter !== null ? $analytics->payload($company, $filter) : null,
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

        return redirect()->route('companies.show', $company);
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
