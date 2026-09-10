<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCall;
use App\Models\AiProviderSetting;
use App\Models\AnalysisSetting;
use App\Models\Call;
use App\Models\Transcript;
use App\Models\TranscriptionProviderSetting;
use App\Models\User;
use App\Services\Pipeline\AnalysisPipelineHealth;
use App\Services\Transcription\ActiveTranscriptionProvider;
use App\Services\Transcription\ElevenLabsTranscriptionClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_phase71_secret_value_aaaa';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
        config(['sales-analyzer.transcription.api_key' => '']);
    }

    public function test_elevenlabs_provider_is_bootstrapped_without_a_key(): void
    {
        $row = TranscriptionProviderSetting::query()->where('provider', 'elevenlabs')->first();

        $this->assertNotNull($row);
        $this->assertSame('ElevenLabs', $row->label);
        $this->assertNull($row->api_key);
        $this->assertSame('scribe_v2', $row->active_model);
        $this->assertTrue($row->is_active);
        $this->assertFalse($row->is_connected);
    }

    public function test_settings_payload_masks_key_and_does_not_leak_secrets(): void
    {
        $user = User::factory()->create();
        $this->saveDatabaseKey($this->secret);

        $response = $this->actingAs($user)->get('/settings?tab=transcription');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index', false)
                ->where('tab', 'transcription')
                ->where('transcription.provider', 'elevenlabs')
                ->where('transcription.has_api_key', true)
                ->where('transcription.source', 'database')
                ->where('transcription.api_key_masked', fn ($value): bool => is_string($value) && ! str_contains($value, $this->secret))
                ->missing('transcription.api_key')
                ->has('pipeline.transcription')
                ->has('pipeline.analysis')
                ->has('analysis_settings.max_output_tokens'));

        $this->assertStringNotContainsString($this->secret, $response->getContent());
    }

    public function test_save_encrypts_key_and_blank_does_not_erase(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/transcription', [
                'api_key' => $this->secret,
                'model' => 'scribe_v2',
            ])
            ->assertRedirect();

        $stored = DB::table('transcription_provider_settings')->where('provider', 'elevenlabs')->value('api_key');
        $this->assertNotSame($this->secret, $stored);
        $this->assertStringNotContainsString($this->secret, (string) $stored);

        $row = TranscriptionProviderSetting::query()->where('provider', 'elevenlabs')->first();
        $this->assertSame($this->secret, $row->api_key);
        $this->assertSame('scribe_v2', $row->active_model);
        $this->assertTrue($row->is_active);
        $this->assertFalse($row->is_connected);

        $this->actingAs($user)
            ->post('/settings/transcription', [
                'api_key' => '',
                'model' => 'scribe_v2',
            ])
            ->assertRedirect();

        $this->assertSame($this->secret, TranscriptionProviderSetting::query()->first()->api_key);
    }

    public function test_replace_key_updates_encrypted_value(): void
    {
        $user = User::factory()->create();
        $this->saveDatabaseKey($this->secret);

        $replacement = 'sk_test_phase71_replacement_bbbb';

        $this->actingAs($user)
            ->post('/settings/transcription', [
                'api_key' => $replacement,
                'model' => 'scribe_v2',
            ])
            ->assertRedirect();

        $row = TranscriptionProviderSetting::query()->first();
        $this->assertSame($replacement, $row->api_key);
        $this->assertFalse($row->is_connected);
        $this->assertStringNotContainsString($replacement, (string) DB::table('transcription_provider_settings')->value('api_key'));
    }

    public function test_check_connection_success_activates_provider(): void
    {
        $user = User::factory()->create();
        $this->saveDatabaseKey($this->secret);

        Http::fake([
            'https://api.elevenlabs.io/v1/user' => Http::response(['subscription' => ['tier' => 'starter']], 200),
        ]);

        $this->actingAs($user)
            ->post('/settings/transcription/check')
            ->assertRedirect();

        $row = TranscriptionProviderSetting::query()->first();
        $this->assertTrue($row->is_connected);
        $this->assertTrue($row->is_active);
        $this->assertNull($row->last_error);
        $this->assertNotNull($row->last_checked_at);
        $this->assertTrue(app(ActiveTranscriptionProvider::class)->isReady());

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.elevenlabs.io/v1/user'
            && $request->method() === 'GET'
            && ! str_contains($request->url(), 'speech-to-text'));
    }

    public function test_check_connection_failure_is_sanitized(): void
    {
        $user = User::factory()->create();
        $this->saveDatabaseKey($this->secret);

        Http::fake([
            'https://api.elevenlabs.io/v1/user' => Http::response(['detail' => $this->secret], 401),
        ]);

        $this->actingAs($user)
            ->from('/settings?tab=transcription')
            ->post('/settings/transcription/check')
            ->assertRedirect('/settings?tab=transcription')
            ->assertSessionHasErrors('transcription');

        $row = TranscriptionProviderSetting::query()->first();
        $this->assertFalse($row->is_connected);
        $this->assertNotNull($row->last_error);
        $this->assertStringNotContainsString($this->secret, (string) $row->last_error);
        $this->assertFalse(app(ActiveTranscriptionProvider::class)->isReady());
    }

    public function test_analysis_settings_are_saved_and_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/analysis', ['max_output_tokens' => 20000])
            ->assertRedirect();

        $this->assertSame(20000, AnalysisSetting::query()->value('max_output_tokens'));
        $this->assertSame('same_as_call', AnalysisSetting::query()->value('report_language_mode'));

        $this->actingAs($user)
            ->from('/settings?tab=ai')
            ->patch('/settings/analysis', ['max_output_tokens' => 12])
            ->assertRedirect('/settings?tab=ai')
            ->assertSessionHasErrors('max_output_tokens');
    }

    public function test_public_upload_is_blocked_when_stt_is_not_ready(): void
    {
        $response = $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('blocked.mp3', 20, 'audio/mpeg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Audio analysis is temporarily unavailable.')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path');

        $this->assertStringNotContainsString('elevenlabs', strtolower($response->getContent()));
        $this->assertStringNotContainsString('ElevenLabs', $response->getContent());
        $this->assertDatabaseCount('calls', 0);
        Queue::assertNothingPushed();
    }

    public function test_public_home_hides_provider_details_when_unavailable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home', false)
                ->where('upload.available', false)
                ->where('upload.unavailable_message', 'Audio analysis is temporarily unavailable.')
                ->missing('upload.provider'));
    }

    public function test_admin_run_analysis_is_rejected_when_ai_is_unavailable(): void
    {
        $user = User::factory()->create();
        $call = Call::factory()->create(['status' => 'transcribed']);
        Transcript::factory()->create(['call_id' => $call->id]);

        $this->actingAs($user)
            ->from('/calls/'.$call->id)
            ->post('/calls/'.$call->id.'/analyze')
            ->assertRedirect()
            ->assertSessionHasErrors('call');

        Queue::assertNotPushed(AnalyzeCall::class);

        $this->actingAs($user)
            ->get('/calls/'.$call->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('call.can_run_analysis', true)
                ->where('call.analysis_ready', false)
                ->where('call.analysis_unavailable_message', fn ($value): bool => is_string($value) && $value !== ''));
    }

    public function test_admin_run_analysis_dispatches_when_ai_is_configured(): void
    {
        $user = User::factory()->create();
        $call = Call::factory()->create(['status' => 'transcribed']);
        Transcript::factory()->create(['call_id' => $call->id]);
        $this->activateOpenAi();

        $this->actingAs($user)
            ->post('/calls/'.$call->id.'/analyze')
            ->assertRedirect();

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
    }

    public function test_elevenlabs_client_uses_resolver_not_env(): void
    {
        $this->assertStringNotContainsString('env(', file_get_contents(app_path('Services/Transcription/ElevenLabsTranscriptionClient.php')));

        $this->saveDatabaseKey($this->secret, connected: true);
        config(['sales-analyzer.transcription.api_key' => 'env-fallback-key-should-not-be-used']);

        $path = 'public/2026/09/sample.mp3';
        Storage::disk(config('sales-analyzer.storage_disk'))->put($path, 'audio-bytes');
        $call = Call::factory()->create([
            'storage_path' => $path,
            'original_filename' => 'sample.mp3',
            'status' => 'uploaded',
        ]);

        Http::fake([
            'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
                'language_code' => 'en',
                'text' => 'Hello',
                'words' => [
                    ['text' => 'Hello', 'type' => 'word', 'start' => 0.0, 'end' => 0.4, 'speaker_id' => 'speaker_0'],
                ],
            ], 200),
        ]);

        app(ElevenLabsTranscriptionClient::class)->transcribe($call);

        Http::assertSent(function ($request) {
            return $request->hasHeader('xi-api-key', $this->secret)
                && str_contains($request->url(), 'speech-to-text');
        });
    }

    public function test_pipeline_health_payload_never_includes_keys(): void
    {
        $this->saveDatabaseKey($this->secret, connected: true);
        $this->activateOpenAi();

        $payload = app(AnalysisPipelineHealth::class)->payload();
        $encoded = json_encode($payload);

        $this->assertTrue($payload['pipeline_ready']);
        $this->assertStringNotContainsString($this->secret, $encoded);
        $this->assertArrayNotHasKey('api_key', $payload['transcription']);
        $this->assertArrayNotHasKey('api_key', $payload['analysis']);
    }

    private function saveDatabaseKey(string $key, bool $connected = false): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => $key,
            'active_model' => 'scribe_v2',
            'is_active' => true,
            'is_connected' => $connected,
            'last_error' => null,
        ])->save();
    }

    private function activateOpenAi(): void
    {
        AiProviderSetting::query()->create([
            'provider' => 'openai',
            'label' => 'OpenAI',
            'api_key' => 'test-openai-key',
            'is_connected' => true,
            'is_active' => true,
            'active_model' => 'gpt-4o-mini',
            'available_models' => [['id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini']],
        ]);
    }
}
