<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyObjection;
use App\Models\CompanyOffering;
use App\Models\CompanyProfile;
use App\Models\CompanySalesScript;
use App\Models\CompanyScorecard;
use App\Models\CompanyScorecardCriterion;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_company_detail(): void
    {
        $company = Company::factory()->create();

        $this->get('/companies/'.$company->id)->assertRedirect('/login');
    }

    public function test_admin_company_detail_uses_tabs(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Acme HVAC']);
        Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Ada']);
        CompanyOffering::factory()->create(['company_id' => $company->id, 'name' => 'Maintenance plan']);
        CompanyScorecard::factory()->create(['company_id' => $company->id, 'name' => 'Inbound']);

        $this->actingAs($user)
            ->get('/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Companies/Show', false)
                ->where('tab', 'overview')
                ->where('company.name', 'Acme HVAC')
                ->has('completeness')
                ->has('offerings')
                ->has('scorecards')
                ->has('employees')
                ->has('calls'));

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=knowledge')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'knowledge')
                ->has('profile'));

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=scorecard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'scorecard')
                ->where('scorecards.0.name', 'Inbound'));
    }

    public function test_profile_is_created_and_updated_as_one_to_one(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->from('/companies/'.$company->id.'?tab=knowledge')
            ->patch('/companies/'.$company->id.'/profile', [
                'target_audience' => 'Homeowners',
                'usp' => '24-hour callback',
                'customer_pains' => 'Winter breakdowns',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('company_profiles', 1);
        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $company->id,
            'usp' => '24-hour callback',
        ]);

        $this->actingAs($user)
            ->patch('/companies/'.$company->id.'/profile', [
                'target_audience' => 'Homeowners',
                'usp' => 'Same-week install',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('company_profiles', 1);
        $this->assertSame('Same-week install', $company->fresh()->profile->usp);
    }

    public function test_second_profile_row_for_the_same_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        CompanyProfile::factory()->create(['company_id' => $company->id]);

        $this->expectException(QueryException::class);
        CompanyProfile::factory()->create(['company_id' => $company->id]);
    }

    public function test_offerings_crud_and_company_isolation(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/offerings', [
                'type' => 'service',
                'name' => 'Annual maintenance',
                'description' => 'Filter change',
            ])
            ->assertRedirect();

        $offering = CompanyOffering::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($offering);

        $this->actingAs($user)
            ->patch('/companies/'.$company->id.'/offerings/'.$offering->id, [
                'type' => 'product',
                'name' => 'Filter kit',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('product', $offering->fresh()->type);
        $this->assertSame('Filter kit', $offering->fresh()->name);

        $this->actingAs($user)
            ->patch('/companies/'.$other->id.'/offerings/'.$offering->id, [
                'type' => 'service',
                'name' => 'Hijack',
            ])
            ->assertNotFound();

        $this->assertSame('Filter kit', $offering->fresh()->name);

        $this->actingAs($user)
            ->delete('/companies/'.$company->id.'/offerings/'.$offering->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('company_offerings', ['id' => $offering->id]);
    }

    public function test_objections_crud(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/objections', [
                'objection' => 'Too expensive',
                'recommended_response' => 'Compare to one emergency visit.',
                'priority' => 10,
            ])
            ->assertRedirect();

        $objection = CompanyObjection::query()->first();
        $this->actingAs($user)
            ->patch('/companies/'.$company->id.'/objections/'.$objection->id, [
                'objection' => 'Too expensive',
                'recommended_response' => 'Show the monthly plan.',
                'priority' => 5,
            ])
            ->assertRedirect();

        $this->assertSame('Show the monthly plan.', $objection->fresh()->recommended_response);

        $this->actingAs($user)
            ->delete('/companies/'.$company->id.'/objections/'.$objection->id)
            ->assertRedirect();

        $this->assertDatabaseCount('company_objections', 0);
    }

    public function test_scripts_crud(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/scripts', [
                'name' => 'Inbound',
                'script_text' => 'Ask system age.',
            ])
            ->assertRedirect();

        $script = CompanySalesScript::query()->first();
        $this->actingAs($user)
            ->patch('/companies/'.$company->id.'/scripts/'.$script->id, [
                'name' => 'Inbound v2',
                'script_text' => 'Ask system age and book an estimate.',
            ])
            ->assertRedirect();

        $this->assertSame('Inbound v2', $script->fresh()->name);

        $this->actingAs($user)
            ->delete('/companies/'.$company->id.'/scripts/'.$script->id)
            ->assertRedirect();

        $this->assertDatabaseCount('company_sales_scripts', 0);
    }

    public function test_scorecards_criteria_weights_default_and_active_flags(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/scorecards', [
                'name' => 'First call',
            ])
            ->assertRedirect();

        $first = CompanyScorecard::query()->first();
        $this->assertTrue($first->is_default);

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/scorecards', [
                'name' => 'Follow up',
                'is_default' => true,
            ])
            ->assertRedirect();

        $second = CompanyScorecard::query()->where('name', 'Follow up')->first();
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/scorecards/'.$first->id.'/criteria', [
                'name' => 'Asked system age',
                'key' => 'system_age',
                'weight' => 30,
                'max_score' => 100,
                'ai_instructions' => 'Did they ask the age of the system?',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/companies/'.$company->id.'/scorecards/'.$first->id.'/criteria', [
                'name' => 'Booked estimate',
                'key' => 'estimate',
                'weight' => 70,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=scorecard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecards.0.total_weight', 100)
                ->where('scorecards.0.weight_warning', false)
                ->where('scorecards.1.total_weight', 0)
                ->where('scorecards.1.weight_warning', true));

        $criterion = CompanyScorecardCriterion::query()->where('key', 'system_age')->first();
        $this->actingAs($user)
            ->patch('/companies/'.$company->id.'/scorecards/'.$first->id.'/criteria/'.$criterion->id, [
                'key' => 'system_age',
                'name' => 'Asked system age',
                'weight' => 20,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=scorecard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecards.0.total_weight', 70)
                ->where('scorecards.0.weight_warning', true));

        $this->actingAs($user)
            ->delete('/companies/'.$company->id.'/scorecards/'.$first->id.'/criteria/'.$criterion->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('company_scorecard_criteria', ['id' => $criterion->id]);
    }

    public function test_knowledge_completeness_is_a_simple_checklist(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->get('/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('completeness.percent', 0));

        CompanyProfile::factory()->create([
            'company_id' => $company->id,
            'target_audience' => 'Homeowners',
            'usp' => '24h callback',
            'customer_pains' => 'Breakdowns',
        ]);
        CompanyOffering::factory()->create(['company_id' => $company->id]);
        CompanySalesScript::factory()->create(['company_id' => $company->id]);
        $scorecard = CompanyScorecard::factory()->create(['company_id' => $company->id]);
        CompanyScorecardCriterion::factory()->create(['scorecard_id' => $scorecard->id]);

        $this->actingAs($user)
            ->get('/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('completeness.percent', 100));
    }

    public function test_company_calls_tab_lists_recent_calls(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        Call::factory()->create([
            'company_id' => $company->id,
            'status' => 'uploaded',
            'original_filename' => 'acme.mp3',
        ]);

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=calls')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'calls')
                ->where('calls.0.original_filename', 'acme.mp3'));
    }
}
