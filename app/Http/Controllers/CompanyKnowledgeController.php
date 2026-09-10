<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyObjectionRequest;
use App\Http\Requests\CompanyOfferingRequest;
use App\Http\Requests\CompanyProfileRequest;
use App\Http\Requests\CompanySalesScriptRequest;
use App\Http\Requests\CompanyScorecardCriterionRequest;
use App\Http\Requests\CompanyScorecardRequest;
use App\Models\Company;
use App\Models\CompanyObjection;
use App\Models\CompanyOffering;
use App\Models\CompanySalesScript;
use App\Models\CompanyScorecard;
use App\Models\CompanyScorecardCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CompanyKnowledgeController extends Controller
{
    public function updateProfile(CompanyProfileRequest $request, Company $company): RedirectResponse
    {
        $company->profile()->updateOrCreate(
            ['company_id' => $company->id],
            $request->validated(),
        );

        return back();
    }

    public function storeOffering(CompanyOfferingRequest $request, Company $company): RedirectResponse
    {
        $company->offerings()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back();
    }

    public function updateOffering(CompanyOfferingRequest $request, Company $company, CompanyOffering $offering): RedirectResponse
    {
        $this->assertCompany($company, $offering->company_id);
        $offering->update($request->validated());

        return back();
    }

    public function destroyOffering(Company $company, CompanyOffering $offering): RedirectResponse
    {
        $this->assertCompany($company, $offering->company_id);
        $offering->delete();

        return back();
    }

    public function storeObjection(CompanyObjectionRequest $request, Company $company): RedirectResponse
    {
        $company->objections()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back();
    }

    public function updateObjection(CompanyObjectionRequest $request, Company $company, CompanyObjection $objection): RedirectResponse
    {
        $this->assertCompany($company, $objection->company_id);
        $objection->update($request->validated());

        return back();
    }

    public function destroyObjection(Company $company, CompanyObjection $objection): RedirectResponse
    {
        $this->assertCompany($company, $objection->company_id);
        $objection->delete();

        return back();
    }

    public function storeScript(CompanySalesScriptRequest $request, Company $company): RedirectResponse
    {
        $company->salesScripts()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back();
    }

    public function updateScript(CompanySalesScriptRequest $request, Company $company, CompanySalesScript $script): RedirectResponse
    {
        $this->assertCompany($company, $script->company_id);
        $script->update($request->validated());

        return back();
    }

    public function destroyScript(Company $company, CompanySalesScript $script): RedirectResponse
    {
        $this->assertCompany($company, $script->company_id);
        $script->delete();

        return back();
    }

    public function storeScorecard(CompanyScorecardRequest $request, Company $company): RedirectResponse
    {
        DB::transaction(function () use ($request, $company): void {
            $data = $request->validated() + [
                'is_active' => $request->boolean('is_active', true),
            ];
            $data['is_default'] = (($data['is_default'] ?? false) === true) || $company->scorecards()->doesntExist();

            if ($data['is_default']) {
                $company->scorecards()->update(['is_default' => false]);
            }

            $company->scorecards()->create($data);
        });

        return back();
    }

    public function updateScorecard(CompanyScorecardRequest $request, Company $company, CompanyScorecard $scorecard): RedirectResponse
    {
        $this->assertCompany($company, $scorecard->company_id);

        DB::transaction(function () use ($request, $company, $scorecard): void {
            $data = $request->validated();
            if (($data['is_default'] ?? false) === true) {
                $company->scorecards()->whereKeyNot($scorecard->id)->update(['is_default' => false]);
            }
            $scorecard->update($data);
        });

        return back();
    }

    public function destroyScorecard(Company $company, CompanyScorecard $scorecard): RedirectResponse
    {
        $this->assertCompany($company, $scorecard->company_id);
        $scorecard->delete();

        return back();
    }

    public function storeCriterion(CompanyScorecardCriterionRequest $request, Company $company, CompanyScorecard $scorecard): RedirectResponse
    {
        $this->assertCompany($company, $scorecard->company_id);
        $data = $request->validated();
        $data['sequence'] = $data['sequence'] ?? ((int) $scorecard->criteria()->max('sequence') + 1);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['max_score'] = $data['max_score'] ?? 100;
        $scorecard->criteria()->create($data);

        return back();
    }

    public function updateCriterion(
        CompanyScorecardCriterionRequest $request,
        Company $company,
        CompanyScorecard $scorecard,
        CompanyScorecardCriterion $criterion,
    ): RedirectResponse {
        $this->assertCompany($company, $scorecard->company_id);
        abort_unless($criterion->scorecard_id === $scorecard->id, 404);
        $criterion->update($request->validated());

        return back();
    }

    public function destroyCriterion(
        Company $company,
        CompanyScorecard $scorecard,
        CompanyScorecardCriterion $criterion,
    ): RedirectResponse {
        $this->assertCompany($company, $scorecard->company_id);
        abort_unless($criterion->scorecard_id === $scorecard->id, 404);
        $criterion->delete();

        return back();
    }

    private function assertCompany(Company $company, int $companyId): void
    {
        abort_unless($company->id === $companyId, 404);
    }
}
