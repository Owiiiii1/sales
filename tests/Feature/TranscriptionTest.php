<?php

namespace Tests\Feature;

use App\Exceptions\Transcription\TransientTranscriptionException;
use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\Company;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\Calls\CallAudioStorage;
use App\Services\Transcription\TranscriptionProvider;
use App\Services\Transcription\TranscriptWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
        config(['sales-analyzer.transcription.api_key' => 'test-key']);
    }

    public function test_public_upload_dispatches_transcription_job(): void
    {
        $this->post('/analyze', ['audio' => $this->fakeAudio()], ['Accept' => 'application/json'])->assertCreated();

        Queue::assertPushed(TranscribeCall::class);
        $this->assertSame('uploaded', Call::query()->value('status'));
    }

    public function test_admin_upload_dispatches_transcription_job(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)->post('/calls', [
            'company_id' => $company->id,
            'audio' => $this->fakeAudio(),
        ])->assertRedirect();

        Queue::assertPushed(TranscribeCall::class);
        $this->assertSame('uploaded', Call::query()->value('status'));
    }

    public function test_job_sets_processing_then_transcribed_and_stores_segments(): void
    {
        $call = $this->callWithAudio();
        $this->fakeSuccessfulProvider();

        $this->runJob($call);

        $call->refresh();
        $this->assertSame('transcribed', $call->status);
        $this->assertSame('en', $call->transcript->language);
        $this->assertSame(8, $call->duration_seconds);
        $this->assertNotNull($call->processing_started_at);
        $this->assertNotNull($call->processing_completed_at);
        $this->assertSame('Hello there. Hi.', $call->transcript->raw_text);
        $this->assertCount(2, $call->transcript->segments);
        $this->assertSame(0, $call->transcript->segments[0]->speaker);
        $this->assertSame(1, $call->transcript->segments[1]->speaker);
        $this->assertSame('Hello there.', $call->transcript->segments[0]->text);
        $this->assertSame('Hi.', $call->transcript->segments[1]->text);
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
    }

    public function test_unsupported_language_fails_without_transcript(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: 'de'), 200),
        ]);

        $this->runJob($call);

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame('Could not detect a supported call language.', $call->error_message);
        $this->assertSame(0, Transcript::query()->count());
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
    }

    public function test_ukrainian_iso_and_bcp47_codes_are_accepted(): void
    {
        foreach (['ukr', 'uk-UA'] as $language) {
            $call = $this->callWithAudio();
            Http::fake([
                'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: $language, text: 'Добрий день.'), 200),
            ]);

            $this->runJob($call);

            $call->refresh();
            $this->assertSame('transcribed', $call->status, $language);
            $this->assertSame('uk', $call->transcript->language, $language);
        }
    }

    public function test_russian_locale_code_is_accepted(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: 'ru-RU', text: 'Здравствуйте.'), 200),
        ]);
        $this->runJob($call);
        $this->assertSame('ru', $call->fresh()->transcript->language);
    }

    public function test_english_locale_code_is_accepted(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: 'en-US'), 200),
        ]);
        $this->runJob($call);
        $this->assertSame('en', $call->fresh()->transcript->language);
    }

    public function test_spanish_without_ukrainian_letters_is_rejected_and_not_retried(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: 'spa', text: 'Hola, ¿cómo estás?'), 200),
        ]);

        $this->runJob($call);

        Http::assertSentCount(1);
        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame('Could not detect a supported call language.', $call->error_message);

        $warning = collect($logged)->first(function (MessageLogged $event) use ($call): bool {
            return $event->level === 'error'
                && $event->message === 'Unsupported transcription language returned by ElevenLabs.'
                && ($event->context['call_id'] ?? null) === $call->id
                && ($event->context['raw_language_code'] ?? null) === 'spa'
                && ($event->context['normalized_language_code'] ?? null) === 'spa'
                && array_key_exists('language_probability', $event->context);
        });
        $this->assertNotNull($warning);

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'Could not detect a supported call language.')
            ->assertJsonMissingPath('raw_language_code')
            ->assertJsonMissingPath('provider_metadata');

        $payload = $this->getJson('/analysis/'.$call->public_token)->json();
        $this->assertStringNotContainsString('spa', json_encode($payload));
        $this->assertStringNotContainsString('Unsupported transcription language returned by ElevenLabs', json_encode($payload));
    }

    public function test_unsupported_code_with_ukrainian_letters_retries_with_ukr(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::sequence()
                ->push($this->providerPayload(language: 'spa', text: 'Скажіть, будь ласка, про тур.'), 200)
                ->push($this->providerPayload(language: 'ukr', text: 'Скажіть, будь ласка, про тур.'), 200, ['request-id' => 'req_uk']),
        ]);

        $this->runJob($call);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_contains($request->body(), 'ukr') || ($request->data()['language_code'] ?? null) === 'ukr';
        });
        $this->assertSame('transcribed', $call->fresh()->status);
        $this->assertSame('uk', $call->fresh()->transcript->language);
    }

    public function test_language_hint_sends_explicit_provider_code(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(language: 'ukr', text: 'Скажіть, будь ласка.'), 200),
        ]);

        $job = new TranscribeCall($call->id, 'uk');
        $job->handle(
            app(TranscriptionProvider::class),
            app(TranscriptWriter::class),
            app(CallAudioStorage::class),
        );

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->body(), 'ukr') || ($request->data()['language_code'] ?? null) === 'ukr';
        });
        $this->assertSame('uk', $call->fresh()->transcript->language);
    }

    public function test_provider_5xx_is_retryable_and_does_not_mark_failed(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response(['detail' => 'unavailable'], 503),
        ]);

        try {
            $this->runJob($call);
            $this->fail('Expected a transient transcription exception.');
        } catch (TransientTranscriptionException) {
            // expected
        }

        $call->refresh();
        $this->assertSame('processing', $call->status);
        $this->assertNull($call->error_message);
        $this->assertSame(0, Transcript::query()->count());
    }

    public function test_provider_permanent_error_fails_and_keeps_audio(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::response(['detail' => 'invalid'], 400),
        ]);

        $this->runJob($call);

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame('Transcription failed. Please try again.', $call->error_message);
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
    }

    public function test_retry_preserves_old_transcript_when_provider_fails(): void
    {
        $call = $this->callWithAudio();
        Http::fake([
            'api.elevenlabs.io/*' => Http::sequence()
                ->push($this->providerPayload(), 200, ['request-id' => 'req_123'])
                ->push(['detail' => 'invalid'], 401),
        ]);
        $this->runJob($call);

        $originalId = $call->fresh()->transcript->id;
        $this->assertSame(2, TranscriptSegment::query()->count());

        $this->runJob($call->fresh());

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame($originalId, $call->transcript->id);
        $this->assertSame(2, TranscriptSegment::query()->count());
    }

    public function test_public_transcript_response_is_safe(): void
    {
        $call = $this->callWithAudio();
        $this->fakeSuccessfulProvider();
        $this->runJob($call);

        $this->getJson('/analysis/'.$call->public_token)
            ->assertOk()
            ->assertJsonPath('status', 'transcribed')
            ->assertJsonPath('message', 'Transcription complete.')
            ->assertJsonPath('language', 'en')
            ->assertJsonPath('transcript.segments.0.speaker_label', 'Speaker 1')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('provider_metadata')
            ->assertJsonMissingPath('transcript.id')
            ->assertJsonMissingPath('transcript.provider_metadata')
            ->assertJsonMissingPath('transcript.provider_request_id');
    }

    public function test_admin_call_detail_includes_transcript(): void
    {
        $user = User::factory()->create();
        $call = $this->callWithAudio();
        $this->fakeSuccessfulProvider();
        $this->runJob($call);

        $this->actingAs($user)
            ->get('/calls/'.$call->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calls/Show', false)
                ->where('call.status', 'transcribed')
                ->where('call.transcript.provider_label', 'ElevenLabs')
                ->where('call.transcript.model_label', 'Scribe v2')
                ->has('call.transcript.segments', 2));
    }

    public function test_admin_can_retry_transcription(): void
    {
        $user = User::factory()->create();
        $call = $this->callWithAudio(['status' => 'failed']);

        $this->actingAs($user)
            ->post('/calls/'.$call->id.'/transcribe')
            ->assertRedirect();

        Queue::assertPushed(TranscribeCall::class, fn (TranscribeCall $job): bool => $job->callId === $call->id);
    }

    public function test_missing_audio_is_a_permanent_failure(): void
    {
        $call = Call::factory()->create([
            'storage_path' => 'missing/file.mp3',
            'status' => 'uploaded',
        ]);

        $this->runJob($call);

        $this->assertSame('failed', $call->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function callWithAudio(array $overrides = []): Call
    {
        $path = 'public/2026/09/sample.mp3';
        Storage::disk(config('sales-analyzer.storage_disk'))->put($path, 'audio-bytes');

        return Call::factory()->create(array_merge([
            'storage_path' => $path,
            'original_filename' => 'sample.mp3',
            'status' => 'uploaded',
            'duration_seconds' => null,
        ], $overrides));
    }

    private function fakeSuccessfulProvider(): void
    {
        Http::fake([
            'api.elevenlabs.io/*' => Http::response($this->providerPayload(), 200, ['request-id' => 'req_123']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerPayload(string $language = 'en', string $text = 'Hello there. Hi.'): array
    {
        return [
            'language_code' => $language,
            'language_probability' => 0.97,
            'text' => $text,
            'audio_duration_secs' => 8.2,
            'transcription_id' => 'tr_1',
            'words' => [
                ['text' => 'Hello', 'type' => 'word', 'start' => 0.0, 'end' => 0.4, 'speaker_id' => 'speaker_0'],
                ['text' => ' there.', 'type' => 'word', 'start' => 0.4, 'end' => 0.9, 'speaker_id' => 'speaker_0'],
                ['text' => 'Hi.', 'type' => 'word', 'start' => 1.0, 'end' => 1.3, 'speaker_id' => 'speaker_1'],
            ],
        ];
    }

    private function runJob(Call $call): void
    {
        $job = new TranscribeCall($call->id);
        $job->handle(
            app(TranscriptionProvider::class),
            app(TranscriptWriter::class),
            app(CallAudioStorage::class),
        );
    }

    private function fakeAudio(string $name = 'sample.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'audio/mpeg');
    }
}
