<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalesAnalysis;
use App\Models\User;
use Database\Factories\SalesAnalysisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_open_analytics_pages(): void
    {
        $employee = Employee::factory()->create();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/employees/'.$employee->id)->assertRedirect('/login');
        $this->get('/companies/'.$employee->company_id.'?tab=analytics')->assertRedirect('/login');
    }

    public function test_empty_dashboard_uses_na_not_zero_scores(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->where('filters.period', 'last_30')
                ->where('analytics.kpis.total_calls', 0)
                ->where('analytics.kpis.average_sales_score', null)
                ->where('analytics.kpis.average_company_score', null)
                ->where('analytics.kpis.analysis_success_rate', null)
                ->where('analytics.kpis.public_analyses', 0)
                ->has('analytics.sections', 7)
                ->missing('analytics.recent_calls.0.storage_path')
                ->missing('prompt'));
    }

    public function test_dashboard_kpis_and_date_filter(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->analyzedCall([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'recorded_at' => '2026-09-01 10:00:00',
            'duration_seconds' => 120,
        ], ['overall_score' => 80]);

        $this->analyzedCall([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'recorded_at' => '2026-09-02 10:00:00',
            'duration_seconds' => 180,
        ], ['overall_score' => 60]);

        Call::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'failed',
            'recorded_at' => '2026-09-03 10:00:00',
            'duration_seconds' => 60,
        ]);

        Call::factory()->create([
            'company_id' => $company->id,
            'recorded_at' => '2026-07-01 10:00:00',
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get('/dashboard?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 3)
                ->where('analytics.kpis.analyzed_calls', 2)
                ->where('analytics.kpis.failed_calls', 1)
                ->where('analytics.kpis.average_sales_score', 70)
                ->where('analytics.kpis.average_duration_seconds', 120)
                ->where('analytics.kpis.analysis_success_rate', 66.7)
                ->where('analytics.kpis.active_employees', 1));
    }

    public function test_public_calls_are_global_only(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->analyzedCall([
            'company_id' => null,
            'employee_id' => null,
            'source' => 'public',
            'recorded_at' => '2026-09-01 10:00:00',
        ], ['overall_score' => 90]);

        $this->analyzedCall([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'recorded_at' => '2026-09-01 11:00:00',
        ], ['overall_score' => 50]);

        $this->actingAs($user)
            ->get('/dashboard?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 2)
                ->where('analytics.kpis.public_analyses', 1)
                ->where('analytics.kpis.average_sales_score', 70));

        $this->actingAs($user)
            ->get('/dashboard?period=last_30&company_id='.$company->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.public_analyses', 0)
                ->where('analytics.kpis.average_sales_score', 50));

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=analytics&period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'analytics')
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 50));

        $this->actingAs($user)
            ->get('/employees/'.$employee->id.'?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Show', false)
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 50));
    }

    public function test_company_and_employee_filters_and_mismatch(): void
    {
        $user = User::factory()->create();
        $acme = Company::factory()->create();
        $beta = Company::factory()->create();
        $ada = Employee::factory()->create(['company_id' => $acme->id, 'first_name' => 'Ada']);
        $bob = Employee::factory()->create(['company_id' => $beta->id, 'first_name' => 'Bob']);

        $this->analyzedCall(['company_id' => $acme->id, 'employee_id' => $ada->id, 'recorded_at' => '2026-09-01 10:00:00'], ['overall_score' => 80]);
        $this->analyzedCall(['company_id' => $beta->id, 'employee_id' => $bob->id, 'recorded_at' => '2026-09-01 10:00:00'], ['overall_score' => 40]);

        $this->actingAs($user)
            ->get('/dashboard?period=last_30&company_id='.$acme->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.company_id', $acme->id)
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80));

        $this->actingAs($user)
            ->get('/dashboard?period=last_30&employee_id='.$ada->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80));

        $this->actingAs($user)
            ->get('/dashboard?period=last_30&company_id='.$acme->id.'&employee_id='.$bob->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.employee_id', null)
                ->where('filters.employee_mismatch', true)
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80));
    }

    public function test_sections_exclude_non_applicable_and_average_correctly(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->analyzedCall(['company_id' => $company->id, 'recorded_at' => '2026-09-01 10:00:00'], [
            'overall_score' => 80,
            'sections' => [
                'opening_rapport' => $this->section(80),
                'objections' => $this->section(10, false),
            ],
        ]);
        $this->analyzedCall(['company_id' => $company->id, 'recorded_at' => '2026-09-02 10:00:00'], [
            'overall_score' => 100,
            'sections' => [
                'opening_rapport' => $this->section(100),
                'objections' => $this->section(20, false),
            ],
        ]);

        $this->actingAs($user)
            ->get('/dashboard?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.sections.0.key', 'opening_rapport')
                ->where('analytics.sections.0.average_score', 90)
                ->where('analytics.sections.0.applicable_count', 2)
                ->where('analytics.sections.4.key', 'objections')
                ->where('analytics.sections.4.average_score', null)
                ->where('analytics.sections.4.applicable_count', 0)
                ->where('analytics.sections.4.call_count', 2));
    }

    public function test_scorecard_uses_snapshots_and_critical_failures(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->analyzedCall(
            ['company_id' => $company->id, 'recorded_at' => '2026-09-01 10:00:00'],
            [
                'overall_score' => 70,
                'company_context_used' => true,
                'company_specific' => $this->companySpecific([
                    ['key' => 'system_age', 'score' => 80, 'max_score' => 100, 'applicable' => true, 'critical_failure' => false],
                    ['key' => 'estimate', 'score' => 40, 'max_score' => 100, 'applicable' => true, 'critical_failure' => true],
                ]),
            ],
            [
                'company_scorecard_score' => 56,
                'scorecard_snapshot' => [
                    'name' => 'Inbound',
                    'criteria' => [
                        ['key' => 'system_age', 'name' => 'Asked system age', 'weight' => 40, 'max_score' => 100],
                        ['key' => 'estimate', 'name' => 'Booked estimate', 'weight' => 60, 'max_score' => 100],
                    ],
                ],
            ],
        );

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=analytics&period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.average_company_score', 56)
                ->where('analytics.scorecard.0.key', 'system_age')
                ->where('analytics.scorecard.0.average_score', 80)
                ->where('analytics.scorecard.0.weight', 40)
                ->where('analytics.scorecard.1.key', 'estimate')
                ->where('analytics.scorecard.1.average_score', 40)
                ->where('analytics.scorecard.1.critical_failure_count', 1));
    }

    public function test_outcomes_mandatory_questions_and_violations(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->analyzedCall(['company_id' => $company->id, 'employee_id' => $employee->id, 'recorded_at' => '2026-09-01 10:00:00'], [
            'call_outcome' => 'sale',
            'customer_intent' => 'high',
            'company_context_used' => true,
            'company_specific' => [
                'mandatory_questions' => ['asked' => ['system age'], 'missed' => ['budget']],
                'forbidden_claims' => ['violations' => [['text' => 'Lifetime warranty']]],
            ],
        ]);
        $this->analyzedCall(['company_id' => $company->id, 'employee_id' => $employee->id, 'recorded_at' => '2026-09-02 10:00:00'], [
            'call_outcome' => 'follow_up',
            'customer_intent' => 'medium',
            'company_context_used' => true,
            'company_specific' => [
                'mandatory_questions' => ['asked' => ['system age'], 'missed' => ['budget']],
                'forbidden_claims' => ['violations' => []],
            ],
        ]);

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=analytics&period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.outcomes.0.key', 'sale')
                ->where('analytics.outcomes.0.count', 1)
                ->where('analytics.intents.0.key', 'high')
                ->where('analytics.intents.0.count', 1)
                ->where('analytics.mandatory_questions.asked_count', 2)
                ->where('analytics.mandatory_questions.missed_count', 2)
                ->where('analytics.mandatory_questions.top_missed.0.text', 'budget')
                ->where('analytics.violations.calls_with_violations', 1)
                ->where('analytics.violations.violation_count', 1)
                ->where('analytics.employees.0.calls', 2));
    }

    public function test_employee_analytics_only_own_calls(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $ada = Employee::factory()->create(['company_id' => $company->id]);
        $bob = Employee::factory()->create(['company_id' => $company->id]);

        $this->analyzedCall(['company_id' => $company->id, 'employee_id' => $ada->id, 'recorded_at' => '2026-09-01 10:00:00'], ['overall_score' => 80]);
        $this->analyzedCall(['company_id' => $company->id, 'employee_id' => $bob->id, 'recorded_at' => '2026-09-01 10:00:00'], ['overall_score' => 20]);

        $this->actingAs($user)
            ->get('/employees/'.$ada->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employee.id', $ada->id)
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80)
                ->where('analytics.recent_calls.0.employee_name', $ada->full_name)
                ->missing('analytics.recent_calls.0.storage_path'));
    }

    public function test_trends_group_by_day_and_period_comparison(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->analyzedCall(['company_id' => $company->id, 'recorded_at' => '2026-09-10 09:00:00'], ['overall_score' => 80]);
        $this->analyzedCall(['company_id' => $company->id, 'recorded_at' => '2026-09-01 09:00:00'], ['overall_score' => 40]);

        $this->actingAs($user)
            ->get('/dashboard?period=last_7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.grouping', 'day')
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80)
                ->where('analytics.comparison.average_sales_score.current', 80)
                ->where('analytics.comparison.average_sales_score.previous', 40)
                ->has('analytics.trends.calls', 7));
    }

    public function test_analytics_payload_has_no_secrets(): void
    {
        $user = User::factory()->create();
        $call = $this->analyzedCall(['recorded_at' => '2026-09-01 10:00:00'], ['overall_score' => 70]);
        $call->forceFill(['storage_path' => 'secret/path.mp3'])->save();

        $this->actingAs($user)
            ->get('/dashboard?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('analytics.recent_calls.0.id')
                ->missing('analytics.recent_calls.0.storage_path')
                ->missing('analytics.recent_calls.0.transcript')
                ->missing('analytics.provider')
                ->missing('api_key'));
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $analysis
     */
    private function analyzedCall(array $call, array $payload = [], array $analysis = []): Call
    {
        $record = Call::factory()->create(array_merge([
            'status' => 'completed',
            'duration_seconds' => 100,
        ], $call));

        $result = SalesAnalysisFactory::validPayload($payload);

        SalesAnalysis::factory()->create(array_merge([
            'call_id' => $record->id,
            'overall_score' => $result['overall_score'],
            'company_scorecard_score' => $analysis['company_scorecard_score'] ?? null,
            'result' => $result,
            'scorecard_snapshot' => $analysis['scorecard_snapshot'] ?? null,
        ], $analysis));

        return $record->fresh(['analysis']);
    }

    /**
     * @return array<string, mixed>
     */
    private function section(int $score, bool $applicable = true): array
    {
        return [
            'applicable' => $applicable,
            'score' => $applicable ? $score : null,
            'summary' => 'n/a',
            'strengths' => [],
            'issues' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function companySpecific(array $criteria): array
    {
        return array_replace_recursive(SalesAnalysisFactory::validPayload()['company_specific'], [
            'scorecard' => [
                'total_score' => 0,
                'criteria' => array_map(fn (array $row): array => array_merge([
                    'summary' => '',
                    'evidence' => [],
                    'critical_failure' => false,
                ], $row), $criteria),
            ],
        ]);
    }
}
