<?php

namespace Tests\Feature;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use App\Jobs\AnalyzeCall;
use App\Jobs\TranscribeCall;
use App\Models\AiProviderSetting;
use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\DTO\SalesAnalysisResult;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisSchema;
use App\Services\Analysis\SalesAnalysisWriter;
use App\Services\Calls\CallAudioStorage;
use App\Services\Transcription\TranscriptionProvider;
use App\Services\Transcription\TranscriptWriter;
use Database\Factories\SalesAnalysisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
        config(['sales-analyzer.transcription.api_key' => 'test-key']);
    }

    public function test_transcribed_call_dispatches_analyze_job(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->sttPayload(), 200, ['request-id' => 'req_123']),
        ]);

        $this->runTranscribe($call);

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
        $this->assertSame('transcribed', $call->fresh()->status);
    }

    public function test_no_transcript_makes_analysis_impossible(): void
    {
        $call = Call::factory()->create(['status' => 'uploaded']);

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_no_provider_config_sets_analysis_pending(): void
    {
        $call = $this->transcribedCall();

        $this->runAnalyze($call);

        $this->assertSame('analysis_pending', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_configured_provider_sets_analyzing_then_completed(): void
    {
        $call = $this->transcribedCall();
        $fake = new class implements SalesAnalysisProvider
        {
            public bool $sawAnalyzing = false;

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(Transcript $transcript, AnalysisContext $context): SalesAnalysisResult
            {
                $this->sawAnalyzing = $transcript->call()->value('status') === 'analyzing';
                $payload = SalesAnalysisFactory::validPayload();

                return new SalesAnalysisResult('openai', 'gpt-4o-mini', SalesAnalysisSchema::VERSION, $payload['overall_score'], $payload['summary'], $payload);
            }
        };
        $this->app->instance(SalesAnalysisProvider::class, $fake);

        $this->runAnalyze($call);

        $call->refresh();
        $this->assertTrue($fake->sawAnalyzing);
        $this->assertSame('completed', $call->status);
        $this->assertSame(72, $call->analysis->overall_score);
        $this->assertSame('The seller opened well and discovered a need, but closing was vague.', $call->analysis->summary);
        $this->assertSame('follow_up', $call->analysis->result['call_outcome']);
        $this->assertSame(SalesAnalysisSchema::VERSION, $call->analysis->schema_version);
        $this->assertNull($call->error_message);
    }

    public function test_malformed_json_is_rejected(): void
    {
        $call = $this->transcribedCall();
        $this->activateOpenAi();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'not-json']]],
            ], 200),
        ]);

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_invalid_score_is_rejected(): void
    {
        $call = $this->transcribedCall();
        $this->bindPayload(SalesAnalysisFactory::validPayload(['overall_score' => 140]));

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_invalid_outcome_is_rejected(): void
    {
        $call = $this->transcribedCall();
        $this->bindPayload(SalesAnalysisFactory::validPayload(['call_outcome' => 'won_big']));

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
    }

    public function test_invalid_speaker_role_is_rejected(): void
    {
        $call = $this->transcribedCall();
        $this->bindPayload(SalesAnalysisFactory::validPayload([
            'speaker_roles' => ['0' => 'manager'],
        ]));

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
    }

    public function test_provider_5xx_is_retryable(): void
    {
        $call = $this->transcribedCall();
        $this->activateOpenAi();
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'unavailable']], 503),
        ]);

        try {
            $this->runAnalyze($call);
            $this->fail('Expected a transient analysis exception.');
        } catch (TransientAnalysisException) {
            // expected
        }

        $this->assertSame('analyzing', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_permanent_provider_failure_is_handled(): void
    {
        $call = $this->transcribedCall();
        $this->activateOpenAi();
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['code' => 'invalid_api_key']], 401),
        ]);

        $this->runAnalyze($call);

        $this->assertSame('failed', $call->fresh()->status);
        $this->assertSame('Analysis failed. Please try again.', $call->fresh()->error_message);
    }

    public function test_previous_successful_analysis_is_preserved_on_failed_rerun(): void
    {
        $call = $this->transcribedCall();
        $payload = SalesAnalysisFactory::validPayload();
        $this->app->instance(SalesAnalysisProvider::class, new class($payload) implements SalesAnalysisProvider
        {
            public int $calls = 0;

            public function __construct(private array $payload) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(Transcript $transcript, AnalysisContext $context): SalesAnalysisResult
            {
                $this->calls++;
                if ($this->calls > 1) {
                    throw new PermanentAnalysisException('schema exploded');
                }

                return new SalesAnalysisResult('openai', 'gpt-4o-mini', SalesAnalysisSchema::VERSION, $this->payload['overall_score'], $this->payload['summary'], $this->payload);
            }
        });

        $this->runAnalyze($call);
        $originalId = $call->fresh()->analysis->id;

        $this->runAnalyze($call->fresh());

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame($originalId, $call->analysis->id);
        $this->assertSame(72, $call->analysis->overall_score);
    }

    public function test_public_report_is_safe_and_complete(): void
    {
        $call = $this->completedCall();

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('report_available', true)
            ->assertJsonPath('report.overall_score', 72)
            ->assertJsonPath('report.call_outcome', 'follow_up')
            ->assertJsonPath('report.sections.opening_rapport.title', 'Opening & Rapport')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('report.provider')
            ->assertJsonMissingPath('report.model')
            ->assertJsonMissingPath('report.schema_version')
            ->assertJsonMissingPath('prompt')
            ->assertJsonMissingPath('api_key');
    }

    public function test_report_language_is_preserved(): void
    {
        $call = $this->transcribedCall('ru');
        $payload = SalesAnalysisFactory::validPayload([
            'summary' => 'Продавец вежливо начал разговор.',
            'next_step' => 'Назначить повторный звонок.',
        ]);
        $this->bindPayload($payload);
        $this->runAnalyze($call);

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('report.summary', 'Продавец вежливо начал разговор.')
            ->assertJsonPath('report.next_step', 'Назначить повторный звонок.');
    }

    public function test_admin_call_detail_includes_analysis(): void
    {
        $user = User::factory()->create();
        $call = $this->completedCall();

        $this->actingAs($user)
            ->get('/calls/'.$call->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calls/Show', false)
                ->where('call.status', 'completed')
                ->where('call.analysis.overall_score', 72)
                ->where('call.analysis.provider_label', 'OpenAI')
                ->where('call.can_rerun_analysis', true));
    }

    public function test_admin_can_run_analysis(): void
    {
        $user = User::factory()->create();
        $call = $this->transcribedCall();

        $this->actingAs($user)
            ->post('/calls/'.$call->id.'/analyze')
            ->assertRedirect();

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
    }

    public function test_admin_can_rerun_completed_analysis(): void
    {
        $user = User::factory()->create();
        $call = $this->completedCall();

        $this->actingAs($user)
            ->post('/calls/'.$call->id.'/analyze')
            ->assertRedirect();

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
    }

    public function test_public_payload_includes_analyzing_and_completed_states(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill(['status' => 'analyzing'])->save();

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'analyzing')
            ->assertJsonPath('message', 'Analyzing your sales call…')
            ->assertJsonPath('report', null)
            ->assertJsonPath('transcript.language', 'en');

        $completed = $this->completedCall();
        $this->getJson('/analysis/'.$completed->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('report.overall_score', 72);
    }

    public function test_analysis_pending_public_message(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill(['status' => 'analysis_pending'])->save();

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'analysis_pending')
            ->assertJsonPath('message', 'Transcription complete. AI analysis is not configured yet.')
            ->assertJsonPath('report', null);
    }

    private function bindPayload(array $payload): void
    {
        $this->app->instance(SalesAnalysisProvider::class, new class($payload) implements SalesAnalysisProvider
        {
            public function __construct(private array $payload) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(Transcript $transcript, AnalysisContext $context): SalesAnalysisResult
            {
                $validator = app(\App\Services\Analysis\SalesAnalysisResultValidator::class);

                return $validator->validate($this->payload, 'openai', 'gpt-4o-mini');
            }
        });
    }

    private function transcribedCall(string $language = 'en'): Call
    {
        $call = Call::factory()->create([
            'status' => 'transcribed',
            'company_id' => null,
        ]);
        $transcript = Transcript::factory()->create([
            'call_id' => $call->id,
            'language' => $language,
            'raw_text' => $language === 'ru'
                ? 'Здравствуйте. Мне интересно ваше предложение.'
                : 'Hello, thanks for taking the time. Hi, I am interested in your offer.',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 0,
            'start_seconds' => 0,
            'end_seconds' => 2,
            'text' => $language === 'ru' ? 'Здравствуйте.' : 'Hello, thanks for taking the time.',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 1,
            'sequence' => 1,
            'start_seconds' => 2.5,
            'end_seconds' => 5,
            'text' => $language === 'ru' ? 'Мне интересно ваше предложение.' : 'Hi, I am interested in your offer.',
        ]);

        return $call->fresh(['transcript.segments']);
    }

    private function completedCall(): Call
    {
        $call = $this->transcribedCall();
        $payload = SalesAnalysisFactory::validPayload();
        SalesAnalysis::factory()->create([
            'call_id' => $call->id,
            'overall_score' => $payload['overall_score'],
            'summary' => $payload['summary'],
            'result' => $payload,
        ]);
        $call->forceFill(['status' => 'completed'])->save();

        return $call->fresh(['transcript.segments', 'analysis']);
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

    private function callWithAudio(): Call
    {
        $path = 'public/2026/09/sample.mp3';
        Storage::disk(config('sales-analyzer.storage_disk'))->put($path, 'audio-bytes');

        return Call::factory()->create([
            'storage_path' => $path,
            'original_filename' => 'sample.mp3',
            'status' => 'uploaded',
            'company_id' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sttPayload(): array
    {
        return [
            'language_code' => 'en',
            'language_probability' => 0.97,
            'text' => 'Hello there. Hi.',
            'audio_duration_secs' => 8.2,
            'words' => [
                ['text' => 'Hello', 'type' => 'word', 'start' => 0.0, 'end' => 0.4, 'speaker_id' => 'speaker_0'],
                ['text' => ' there.', 'type' => 'word', 'start' => 0.4, 'end' => 0.9, 'speaker_id' => 'speaker_0'],
                ['text' => 'Hi.', 'type' => 'word', 'start' => 1.0, 'end' => 1.3, 'speaker_id' => 'speaker_1'],
            ],
        ];
    }

    private function runTranscribe(Call $call): void
    {
        $job = new TranscribeCall($call->id);
        $job->handle(
            app(TranscriptionProvider::class),
            app(TranscriptWriter::class),
            app(CallAudioStorage::class),
        );
    }

    private function runAnalyze(Call $call): void
    {
        $job = new AnalyzeCall($call->id);
        $job->handle(
            app(SalesAnalysisProvider::class),
            app(SalesAnalysisWriter::class),
        );
    }
}
