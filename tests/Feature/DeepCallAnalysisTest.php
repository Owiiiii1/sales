<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCall;
use App\Models\AiProviderSetting;
use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Models\User;
use App\Services\Analysis\DTO\SalesAnalysisResult;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisPromptBuilder;
use App\Services\Analysis\SalesAnalysisSchema;
use App\Services\Analysis\SalesAnalysisWriter;
use App\Support\SalesAnalysisPresenter;
use Database\Factories\SalesAnalysisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\DeepSalesCall;
use Tests\TestCase;

class DeepCallAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('sales-analyzer.storage_disk'));
    }

    public function test_public_generic_call_stores_schema_v3_and_metrics(): void
    {
        $call = $this->transcribedDeepCall();
        $this->bindPayload(SalesAnalysisFactory::validPayload());
        $this->runAnalyze($call);

        $call->refresh()->load(['analysis', 'transcript.segments']);
        $this->assertSame('completed', $call->status);
        $this->assertSame(3, $call->analysis->schema_version);
        $this->assertSame('The seller opened well, found interest, then left without a booked next step.', $call->analysis->result['executive_summary']['one_sentence']);
        $this->assertFalse($call->analysis->result['negotiation']['applicable']);
        $this->assertSame('follow_up', $call->analysis->result['outcome_analysis']['actual_outcome']);
        $this->assertNotNull($call->analysis->result['conversation_metrics']['seller_talk_percent']);
        $this->assertGreaterThan(0, $call->analysis->result['conversation_metrics']['speaker_switches']);
        $this->assertFalse($call->analysis->result['company_context_used']);
    }

    public function test_public_payload_includes_deep_blocks_without_secrets(): void
    {
        $call = $this->completedDeepCall();

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('report.executive_summary.biggest_problem', 'No calendar commitment before hanging up.')
            ->assertJsonPath('report.conversation_control.who_led', 'balanced')
            ->assertJsonPath('report.closing.next_step_specificity', 'vague')
            ->assertJsonPath('report.timeline.0.type', 'positive')
            ->assertJsonPath('report.coaching_priorities.0.priority', 1)
            ->assertJsonPath('report.negotiation.applicable', false)
            ->assertJsonMissingPath('report.provider')
            ->assertJsonMissingPath('report.model')
            ->assertJsonMissingPath('report.schema_version')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('api_key')
            ->assertJsonMissingPath('prompt');
    }

    public function test_legacy_v2_analysis_is_still_presentable(): void
    {
        $call = $this->transcribedDeepCall();
        $payload = SalesAnalysisFactory::legacyV2Payload();
        SalesAnalysis::factory()->create([
            'call_id' => $call->id,
            'schema_version' => 2,
            'overall_score' => 72,
            'summary' => $payload['summary'],
            'result' => $payload,
        ]);
        $call->forceFill(['status' => 'completed'])->save();

        $presented = SalesAnalysisPresenter::public($call->fresh('analysis'));
        $this->assertSame(72, $presented['overall_score']);
        $this->assertSame('follow_up', $presented['call_outcome']);
        $this->assertNull($presented['executive_summary']);
        $this->assertSame([], $presented['timeline']);
        $this->assertSame('Can we book a 20-minute call on Thursday at 10?', $presented['better_phrases'][0]['suggested']);

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('report.overall_score', 72)
            ->assertJsonPath('report.summary', $payload['summary']);
    }

    public function test_admin_renders_v3_and_company_specific_block_is_preserved(): void
    {
        $user = User::factory()->create();
        $call = $this->completedDeepCall();

        $this->actingAs($user)
            ->get('/calls/'.$call->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calls/Show', false)
                ->where('call.analysis.schema_version', 3)
                ->where('call.analysis.executive_summary.best_next_action', 'Email two specific time options today and confirm the customer’s main need.')
                ->where('call.analysis.provider_label', 'OpenAI')
                ->where('call.analysis.company_specific.scorecard.total_score', null));
    }

    public function test_prompt_requires_evidence_and_v3_limits(): void
    {
        $call = $this->transcribedDeepCall();
        $transcript = $call->transcript;
        $messages = app(SalesAnalysisPromptBuilder::class)->messages(
            $transcript,
            new \App\Services\Analysis\DTO\AnalysisContext(language: 'en'),
        );

        $this->assertStringContainsString('Evidence first', $messages['system']);
        $this->assertStringContainsString('Forbidden fluff', $messages['system']);
        $this->assertStringContainsString('schema_version: 3', $messages['user']);
        $this->assertStringContainsString('full deep v3 analysis', $messages['user']);
        $this->assertStringContainsString('coaching_priorities <= 5', $messages['system']);
    }

    private function transcribedDeepCall(): Call
    {
        $path = 'public/2026/09/deep.mp3';
        Storage::disk(config('sales-analyzer.storage_disk'))->put($path, 'audio-bytes');

        $call = Call::factory()->create([
            'storage_path' => $path,
            'original_filename' => 'deep.mp3',
            'status' => 'transcribed',
            'company_id' => null,
        ]);

        return DeepSalesCall::attachTo($call);
    }

    private function completedDeepCall(): Call
    {
        $call = $this->transcribedDeepCall();
        $this->bindPayload(SalesAnalysisFactory::validPayload());
        $this->runAnalyze($call);

        return $call->fresh(['transcript.segments', 'analysis']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindPayload(array $payload): void
    {
        $this->activateOpenAi();
        $this->app->instance(SalesAnalysisProvider::class, new class($payload) implements SalesAnalysisProvider
        {
            public function __construct(private array $payload) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(\App\Models\Transcript $transcript, \App\Services\Analysis\DTO\AnalysisContext $context): SalesAnalysisResult
            {
                return app(\App\Services\Analysis\SalesAnalysisResultValidator::class)->validate(
                    $this->payload,
                    'openai',
                    'gpt-4o-mini',
                    $context,
                );
            }
        });
    }

    private function runAnalyze(Call $call): void
    {
        $job = new AnalyzeCall($call->id);
        $job->handle(
            app(SalesAnalysisProvider::class),
            app(SalesAnalysisWriter::class),
            app(\App\Services\Analysis\AnalysisContextBuilder::class),
        );
    }

    private function activateOpenAi(): void
    {
        AiProviderSetting::query()->create([
            'provider' => 'openai',
            'label' => 'OpenAI',
            'api_key' => 'test-key',
            'is_connected' => true,
            'is_active' => true,
            'active_model' => 'gpt-4o-mini',
            'available_models' => [['id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini']],
        ]);
    }
}
