<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCall;
use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Models\Transcript;
use App\Services\Analysis\AnalysisContextBuilder;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\DTO\SalesAnalysisResult;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisSchema;
use App\Services\Analysis\SalesAnalysisWriter;
use App\Services\Calls\CallAudioStorage;
use App\Services\Transcription\DTO\TranscriptionResult;
use App\Services\Transcription\DTO\TranscriptionSegment;
use App\Services\Transcription\TranscriptionProvider;
use App\Services\Transcription\TranscriptWriter;
use Database\Factories\SalesAnalysisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CallCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
        config(['sales-analyzer.transcription.api_key' => 'test-key']);
    }

    public function test_cancel_uploaded_call(): void
    {
        $token = $this->uploadPublicCall();
        $call = Call::query()->where('public_token', $token)->first();

        $this->post('/analysis/'.$token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('progress.headline', 'stopped')
            ->assertJsonPath('progress.steps.1.state', 'cancelled')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path');

        $call->refresh();
        $this->assertSame('cancelled', $call->status);
        $this->assertSame('transcription', $call->cancelled_stage);
        $this->assertNotNull($call->cancelled_at);
    }

    public function test_cancel_processing_call(): void
    {
        $call = $this->callWithAudio(['status' => 'processing']);

        $this->post('/analysis/'.$call->public_token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertSame('transcription', $call->fresh()->cancelled_stage);
    }

    public function test_cancel_transcribed_call(): void
    {
        $call = $this->transcribedCall();

        $this->post('/analysis/'.$call->public_token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('progress.steps.2.state', 'cancelled')
            ->assertJsonPath('transcript_available', true)
            ->assertJsonPath('transcript.text', 'Hello there. Hi.');

        $this->assertSame('preparing', $call->fresh()->cancelled_stage);
    }

    public function test_cancel_analyzing_call(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill(['status' => 'analyzing'])->save();

        $this->post('/analysis/'.$call->public_token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('progress.headline', 'stopped_analysis')
            ->assertJsonPath('progress.steps.3.state', 'cancelled');

        $this->assertSame('analysis', $call->fresh()->cancelled_stage);
    }

    public function test_cannot_cancel_completed_call(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill(['status' => 'completed'])->save();

        $this->post('/analysis/'.$call->public_token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'completed')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');

        $this->assertSame('completed', $call->fresh()->status);
        $this->assertNull($call->fresh()->cancelled_at);
    }

    public function test_repeated_cancel_is_idempotent(): void
    {
        $token = $this->uploadPublicCall();

        $this->post('/analysis/'.$token.'/cancel', [], ['Accept' => 'application/json'])->assertOk();
        $first = Call::query()->where('public_token', $token)->first();

        $this->post('/analysis/'.$token.'/cancel', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertSame($first->cancelled_at?->timestamp, Call::query()->where('public_token', $token)->first()->cancelled_at?->timestamp);
        $this->assertSame(1, Call::query()->count());
    }

    public function test_public_cancel_rejects_raw_call_id(): void
    {
        $call = $this->callWithAudio();

        $this->post('/analysis/'.$call->id.'/cancel', [], ['Accept' => 'application/json'])->assertNotFound();
        $this->assertSame('uploaded', $call->fresh()->status);
    }

    public function test_transcribe_job_respects_cancelled_before_start(): void
    {
        $call = $this->callWithAudio();
        $call->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_stage' => 'transcription',
        ])->save();

        $this->runTranscribe($call);

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertNull($call->fresh()->transcript);
        Queue::assertNotPushed(AnalyzeCall::class);
    }

    public function test_late_transcription_response_does_not_start_analysis(): void
    {
        $call = $this->callWithAudio(['status' => 'processing']);

        $this->app->instance(TranscriptionProvider::class, new class implements TranscriptionProvider
        {
            public function transcribe(Call $call): TranscriptionResult
            {
                $call->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_stage' => 'transcription',
                ])->save();

                return new TranscriptionResult(
                    'elevenlabs',
                    'scribe_v2',
                    'en',
                    'Hello there. Hi.',
                    8.0,
                    0.9,
                    'req_cancel',
                    [
                        new TranscriptionSegment(0, 0.0, 1.0, 'Hello there.'),
                        new TranscriptionSegment(1, 1.0, 2.0, 'Hi.'),
                    ],
                );
            }
        });

        $this->runTranscribe($call);

        $call->refresh();
        $this->assertSame('cancelled', $call->status);
        $this->assertNotNull($call->transcript);
        $this->assertSame('Hello there. Hi.', $call->transcript->raw_text);
        Queue::assertNotPushed(AnalyzeCall::class);
    }

    public function test_analyze_job_respects_cancelled_before_start(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_stage' => 'preparing',
        ])->save();

        $this->runAnalyze($call);

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_late_analysis_response_does_not_complete_cancelled_call(): void
    {
        $call = $this->transcribedCall();
        $call->forceFill(['status' => 'analyzing'])->save();

        $this->app->instance(SalesAnalysisProvider::class, new class($call->id) implements SalesAnalysisProvider
        {
            public function __construct(private int $callId) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(\App\Models\Transcript $transcript, AnalysisContext $context): SalesAnalysisResult
            {
                \App\Models\Call::query()->whereKey($this->callId)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_stage' => 'analysis',
                ]);

                $payload = SalesAnalysisFactory::validPayload();

                return new SalesAnalysisResult('openai', 'gpt-4o-mini', SalesAnalysisSchema::VERSION, $payload['overall_score'], $payload['summary'], $payload);
            }
        });

        $this->runAnalyze($call);

        $call->refresh();
        $this->assertSame('cancelled', $call->status);
        $this->assertSame(0, SalesAnalysis::query()->count());
    }

    public function test_home_keeps_transcript_in_modal_and_stops_polling_on_cancel(): void
    {
        $home = file_get_contents(resource_path('js/Pages/Public/Home.jsx'));

        $this->assertStringNotContainsString('<CallTranscript', $home);
        $this->assertStringContainsString('TranscriptReadyCard', $home);
        $this->assertStringContainsString('TranscriptModal', $home);
        $this->assertStringContainsString('ProcessingProgress', $home);
        $this->assertStringContainsString('CancelProcessingModal', $home);
        $this->assertStringContainsString("'cancelled'", $home);
        $this->assertStringContainsString('TERMINAL_STATUSES', $home);
        $this->assertStringContainsString('analysis.cancel', $home);
    }

    public function test_progress_payload_does_not_leak_provider_errors(): void
    {
        $call = $this->callWithAudio(['status' => 'failed', 'error_message' => 'Analysis failed. Please try again.']);

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('progress.steps.1.state', 'failed')
            ->assertJsonPath('error', 'Transcription failed. Please try again.')
            ->assertJsonMissingPath('storage_path')
            ->assertDontSee('api_key')
            ->assertDontSee('stack');
    }

    private function uploadPublicCall(): string
    {
        $response = $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('sample.mp3', 20, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        return $response->json('public_token');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function callWithAudio(array $overrides = []): Call
    {
        $path = 'public/2026/09/cancel-sample.mp3';
        Storage::disk(config('sales-analyzer.storage_disk'))->put($path, 'audio-bytes');

        return Call::factory()->create(array_merge([
            'storage_path' => $path,
            'original_filename' => 'sample.mp3',
            'status' => 'uploaded',
            'source' => 'public',
        ], $overrides));
    }

    private function transcribedCall(): Call
    {
        $call = $this->callWithAudio(['status' => 'transcribed']);
        Transcript::factory()->create([
            'call_id' => $call->id,
            'raw_text' => 'Hello there. Hi.',
        ]);

        return $call->fresh(['transcript.segments']);
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
            app(AnalysisContextBuilder::class),
        );
    }
}
