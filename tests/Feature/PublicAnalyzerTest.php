<?php

namespace Tests\Feature;

use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\CompanyScorecard;
use App\Models\Employee;
use App\Models\SalesAnalysis;
use App\Models\User;
use App\Services\Analysis\AnalysisContextBuilder;
use Database\Factories\SalesAnalysisFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales-analyzer.transcription.api_key' => 'test-key']);
        Storage::fake(config('sales-analyzer.storage_disk'));
    }

    public function test_guest_can_upload_valid_audio_as_a_public_call(): void
    {
        $response = $this->post('/analyze', [
            'audio' => $this->fakeAudio('public-call.mp3'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('analysis_mode', 'generic')
            ->assertJsonPath('company_name', null)
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('uploaded_by')
            ->assertJsonMissingPath('company_id');

        $token = $response->json('public_token');
        $this->assertTrue(Str::isUuid($token));

        $call = Call::query()->where('public_token', $token)->first();
        $this->assertNotNull($call);
        $this->assertNull($call->company_id);
        $this->assertNull($call->employee_id);
        $this->assertNull($call->uploaded_by);
        $this->assertSame('public', $call->source);
        $this->assertSame('uploaded', $call->status);
        $this->assertSame('public-call.mp3', $call->original_filename);
        $this->assertStringStartsWith('public/', $call->storage_path);
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
        Queue::assertPushed(TranscribeCall::class, fn (TranscribeCall $job): bool => $job->callId === $call->id);

        $context = app(AnalysisContextBuilder::class)->build($call->fresh(['transcript', 'company']));
        $this->assertFalse($context->companyContextUsed);
        $this->assertNull($context->companyId);
    }

    public function test_home_payload_lists_active_companies_and_employees_without_knowledge_secrets(): void
    {
        $active = Company::factory()->create(['name' => 'Visible Co', 'is_active' => true]);
        Company::factory()->inactive()->create(['name' => 'Hidden Co']);
        CompanyProfile::factory()->create([
            'company_id' => $active->id,
            'sales_context' => 'SECRET_SALES_CONTEXT',
            'forbidden_claims' => 'SECRET_FORBIDDEN_CLAIM',
            'notes' => 'SECRET_INTERNAL_NOTE',
            'usp' => 'Visible USP',
        ]);
        $scorecard = CompanyScorecard::factory()->create([
            'company_id' => $active->id,
            'name' => 'Main Sales Scorecard',
            'is_default' => true,
        ]);
        $activeEmployee = Employee::factory()->create([
            'company_id' => $active->id,
            'first_name' => 'Ada',
            'last_name' => 'Sales',
            'email' => 'ada-secret@example.com',
            'is_active' => true,
        ]);
        Employee::factory()->inactive()->create([
            'company_id' => $active->id,
            'first_name' => 'Inactive',
            'last_name' => 'Rep',
        ]);
        $other = Company::factory()->inactive()->create();
        Employee::factory()->create(['company_id' => $other->id, 'first_name' => 'Other']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home', false)
                ->has('companies', 1)
                ->where('companies.0.id', $active->id)
                ->where('companies.0.name', 'Visible Co')
                ->where('companies.0.scorecard_name', 'Main Sales Scorecard')
                ->has('companies.0.knowledge_completeness')
                ->missing('companies.0.sales_context')
                ->missing('companies.0.forbidden_claims')
                ->missing('companies.0.notes')
                ->missing('companies.0.usp')
                ->has('employees', 1)
                ->where('employees.0.id', $activeEmployee->id)
                ->where('employees.0.company_id', $active->id)
                ->where('employees.0.name', 'Ada Sales')
                ->missing('employees.0.email')
                ->missing('employees.0.phone')
                ->has('companies.0', fn (Assert $company) => $company
                    ->where('id', $active->id)
                    ->where('name', 'Visible Co')
                    ->where('scorecard_name', 'Main Sales Scorecard')
                    ->has('knowledge_completeness'))
                ->has('employees.0', fn (Assert $employee) => $employee
                    ->where('id', $activeEmployee->id)
                    ->where('company_id', $active->id)
                    ->where('name', 'Ada Sales')))
            ->assertDontSee('SECRET_SALES_CONTEXT')
            ->assertDontSee('SECRET_FORBIDDEN_CLAIM')
            ->assertDontSee('SECRET_INTERNAL_NOTE')
            ->assertDontSee('ada-secret@example.com');

        $this->assertNotNull($scorecard);
    }

    public function test_home_has_no_preselected_analysis_context(): void
    {
        Company::factory()->create(['name' => 'YFS']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home', false)
                ->missing('company_id')
                ->missing('employee_id')
                ->missing('selected_company_id')
                ->missing('analysis_mode')
                ->has('companies', 1)
                ->has('employees'));
    }

    public function test_home_ui_requires_explicit_context_before_analyze(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Public/Home.jsx'));

        $this->assertStringContainsString("const GENERIC_CONTEXT = 'generic'", $source);
        $this->assertStringContainsString("useState('')", $source);
        $this->assertStringContainsString("t('home.selectContext')", $source);
        $this->assertStringContainsString('<option value={GENERIC_CONTEXT}>', $source);
        $this->assertStringContainsString("t('home.genericOption')", $source);
        $this->assertStringContainsString('disabled={!uploadAvailable || !contextSelected}', $source);
        $this->assertStringContainsString("t('home.selectContextFirst')", $source);
        $this->assertStringContainsString('disabled={!selectedCompany}', $source);
        $this->assertStringContainsString('companyIdRef.current !== GENERIC_CONTEXT', $source);
        $this->assertStringContainsString("setEmployeeId('')", $source);
        $this->assertStringContainsString('{contextSelected && (', $source);
        $this->assertStringContainsString("t('home.genericSummaryMethod')", $source);
        $this->assertStringContainsString("t('home.genericSummaryRules')", $source);
        $this->assertStringNotContainsString("<option value=\"\">{t('home.genericOption')}</option>", $source);
    }

    public function test_home_does_not_render_full_transcript_inline(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Public/Home.jsx'));

        $this->assertStringNotContainsString('<CallTranscript', $source);
        $this->assertStringContainsString('TranscriptReadyCard', $source);
        $this->assertStringContainsString('TranscriptModal', $source);
        $this->assertStringContainsString('ProcessingProgress', $source);
    }

    public function test_explicit_generic_upload_keeps_company_and_employee_null(): void
    {
        $response = $this->post('/analyze', [
            'audio' => $this->fakeAudio('generic-explicit.mp3'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('analysis_mode', 'generic')
            ->assertJsonPath('company_name', null)
            ->assertJsonPath('employee_name', null);

        $call = Call::query()->where('public_token', $response->json('public_token'))->first();
        $this->assertNotNull($call);
        $this->assertNull($call->company_id);
        $this->assertNull($call->employee_id);
        $this->assertSame('public', $call->source);
    }

    public function test_explicit_company_upload_without_employee_is_allowed(): void
    {
        $company = Company::factory()->create(['name' => 'YFS']);

        $response = $this->post('/analyze', [
            'audio' => $this->fakeAudio('company-only.mp3'),
            'company_id' => $company->id,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('analysis_mode', 'company')
            ->assertJsonPath('company_name', 'YFS')
            ->assertJsonPath('employee_name', null);

        $call = Call::query()->where('public_token', $response->json('public_token'))->first();
        $this->assertSame($company->id, $call->company_id);
        $this->assertNull($call->employee_id);
        $this->assertSame('public', $call->source);
    }

    public function test_public_tokens_are_unique(): void
    {
        $this->post('/analyze', ['audio' => $this->fakeAudio('a.mp3')], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('b.mp3')], ['Accept' => 'application/json'])->assertCreated();

        $tokens = Call::query()->pluck('public_token');
        $this->assertCount(2, $tokens);
        $this->assertCount(2, $tokens->unique());
    }

    public function test_invalid_audio_is_rejected(): void
    {
        $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_oversized_audio_is_rejected(): void
    {
        config([
            'sales-analyzer.max_audio_size_mb' => 1,
            'sales-analyzer.max_audio_size_kb' => 1,
        ]);

        $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('huge.mp3', 20, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_guest_cannot_override_source_or_uploader(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $company->id,
            'uploaded_by' => $user->id,
            'source' => 'manual',
        ], ['Accept' => 'application/json'])->assertCreated();

        $call = Call::query()->first();
        $this->assertSame($company->id, $call->company_id);
        $this->assertNull($call->uploaded_by);
        $this->assertSame('public', $call->source);
    }

    public function test_guest_can_upload_with_company_and_employee(): void
    {
        $company = Company::factory()->create(['name' => 'Acme HVAC']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jane',
            'last_name' => 'Seller',
        ]);
        CompanyProfile::factory()->create(['company_id' => $company->id]);

        $response = $this->post('/analyze', [
            'audio' => $this->fakeAudio('company-call.mp3'),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('analysis_mode', 'company')
            ->assertJsonPath('company_name', 'Acme HVAC')
            ->assertJsonPath('employee_name', 'Jane Seller')
            ->assertJsonMissingPath('company_id')
            ->assertJsonMissingPath('employee_id');

        $call = Call::query()->where('public_token', $response->json('public_token'))->first();
        $this->assertSame($company->id, $call->company_id);
        $this->assertSame($employee->id, $call->employee_id);
        $this->assertSame('public', $call->source);
        $this->assertNull($call->uploaded_by);
        $this->assertStringStartsWith($company->id.'/', $call->storage_path);

        $context = app(AnalysisContextBuilder::class)->build($call->fresh(['transcript', 'company']));
        $this->assertTrue($context->companyContextUsed);
        $this->assertSame($company->id, $context->companyId);
    }

    public function test_employee_without_company_is_rejected(): void
    {
        $employee = Employee::factory()->create();

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'employee_id' => $employee->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_employee_from_another_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $other->id]);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_inactive_company_and_employee_are_rejected(): void
    {
        $inactiveCompany = Company::factory()->inactive()->create();
        $activeCompany = Company::factory()->create();
        $inactiveEmployee = Employee::factory()->inactive()->create(['company_id' => $activeCompany->id]);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $inactiveCompany->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $activeCompany->id,
            'employee_id' => $inactiveEmployee->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_analyzer_company_call_is_included_in_company_and_employee_analytics(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ], ['Accept' => 'application/json'])->assertCreated();

        $call = Call::query()->first();
        $call->forceFill([
            'status' => 'completed',
            'recorded_at' => '2026-09-01 10:00:00',
        ])->save();

        $result = SalesAnalysisFactory::validPayload(['overall_score' => 80]);
        SalesAnalysis::factory()->create([
            'call_id' => $call->id,
            'overall_score' => 80,
            'result' => $result,
        ]);

        $generic = Call::factory()->create([
            'company_id' => null,
            'employee_id' => null,
            'source' => 'public',
            'status' => 'completed',
            'recorded_at' => '2026-09-01 11:00:00',
        ]);
        SalesAnalysis::factory()->create([
            'call_id' => $generic->id,
            'overall_score' => 40,
            'result' => SalesAnalysisFactory::validPayload(['overall_score' => 40]),
        ]);

        $this->actingAs($user)
            ->get('/companies/'.$company->id.'?tab=analytics&period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80));

        $this->actingAs($user)
            ->get('/employees/'.$employee->id.'?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 1)
                ->where('analytics.kpis.average_sales_score', 80));

        $this->actingAs($user)
            ->get('/dashboard?period=last_30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_calls', 2)
                ->where('analytics.kpis.public_analyses', 1));
    }

    public function test_status_endpoint_returns_safe_payload_for_a_valid_token(): void
    {
        $this->post('/analyze', [
            'audio' => $this->fakeAudio('safe.mp3'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $token = Call::query()->value('public_token');

        $this->getJson("/analysis/{$token}/status")
            ->assertOk()
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('report_available', false)
            ->assertJsonPath('progress.cancellable', true)
            ->assertJsonPath('progress.steps.0.state', 'completed')
            ->assertJsonPath('progress.steps.1.state', 'active')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('uploaded_by');

        $this->getJson("/analysis/{$token}")
            ->assertOk()
            ->assertJsonPath('report', null)
            ->assertJsonPath('transcript', null)
            ->assertJsonPath('message', 'Your call is queued for transcription.')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path');
    }

    public function test_invalid_public_token_returns_404(): void
    {
        $this->getJson('/analysis/'.Str::uuid().'/status')->assertNotFound();
        $this->getJson('/analysis/1/status')->assertNotFound();
        $this->get('/analysis/'.Str::uuid().'/full')->assertNotFound();
    }

    public function test_upload_stores_selected_ui_locale_not_later_session_locale(): void
    {
        $this->withSession(['locale' => 'en']);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio('locale.mp3'),
            'locale' => 'ru',
        ], ['Accept' => 'application/json'])->assertCreated();

        $call = Call::query()->first();
        $this->assertSame('ru', $call->ui_locale);

        $this->withSession(['locale' => 'uk']);
        app()->setLocale('uk');

        $context = app(AnalysisContextBuilder::class)->build($call->fresh(['transcript', 'company']));
        $this->assertSame('ru', $context->reportLanguage);
    }

    public function test_completed_main_analyzer_exposes_short_report_and_full_page_without_new_job(): void
    {
        $this->post('/analyze', [
            'audio' => $this->fakeAudio('done.mp3'),
            'locale' => 'en',
        ], ['Accept' => 'application/json'])->assertCreated();

        $call = Call::query()->first();
        $call->forceFill(['status' => 'completed'])->save();

        $repeats = [];
        for ($i = 1; $i <= 5; $i++) {
            $repeats[] = ['text' => 'Strength '.$i, 'why' => 'Why '.$i];
        }

        SalesAnalysis::factory()->create([
            'call_id' => $call->id,
            'overall_score' => 72,
            'result' => SalesAnalysisFactory::validPayload([
                'what_to_repeat' => $repeats,
                'critical_mistakes' => [
                    [
                        'timestamp_seconds' => null,
                        'mistake' => '',
                        'impact' => 'high',
                        'why' => '',
                        'better_action' => '',
                        'example_phrase' => '',
                    ],
                    [
                        'timestamp_seconds' => 8,
                        'mistake' => 'No next step.',
                        'impact' => 'high',
                        'why' => 'Interest was left hanging.',
                        'better_action' => 'Book a time.',
                        'example_phrase' => 'Thursday at 10?',
                    ],
                ],
            ]),
        ]);

        Queue::fake();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Home', false));

        $json = $this->getJson('/analysis/'.$call->public_token);
        $json->assertOk()
            ->assertJsonPath('report.overall_score', 72)
            ->assertJsonPath('full_report_url', route('analysis.full', $call->public_token))
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('uploaded_by')
            ->assertJsonMissingPath('report.api_key')
            ->assertJsonMissingPath('report.context_snapshot');

        $this->assertCount(3, $json->json('report.short.strengths'));
        $this->assertCount(1, $json->json('report.critical_mistakes'));
        $this->assertSame('No next step.', $json->json('report.critical_mistakes.0.mistake'));

        $this->get('/analysis/'.$call->public_token.'/full')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/FullReport', false)
                ->where('result.public_token', $call->public_token)
                ->where('result.report.overall_score', 72)
                ->where('result.report.critical_mistakes.0.mistake', 'No next step.')
                ->missing('result.id')
                ->missing('result.storage_path')
                ->missing('result.api_key'));

        Queue::assertNothingPushed();
    }

    public function test_admin_audio_endpoints_still_require_auth(): void
    {
        $call = Call::factory()->create([
            'storage_path' => '1/2026/09/missing.mp3',
        ]);

        $this->get("/calls/{$call->id}/audio")->assertRedirect('/login');
        $this->get("/calls/{$call->id}/download")->assertRedirect('/login');
    }

    public function test_public_upload_is_rate_limited(): void
    {
        RateLimiter::for(
            'public-analyze',
            fn () => Limit::perMinute(2)->by('public-analyze-test'),
        );

        $headers = ['Accept' => 'application/json'];

        $this->post('/analyze', ['audio' => $this->fakeAudio('one.mp3')], $headers)->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('two.mp3')], $headers)->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('three.mp3')], $headers)->assertStatus(429);
    }

    private function fakeAudio(string $name = 'sample.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'audio/mpeg');
    }
}
