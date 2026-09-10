<?php

namespace Tests\Feature;

use App\Models\AiProviderSetting;
use App\Services\Pipeline\AnalysisPipelineHealth;
use App\Services\Transcription\ActiveTranscriptionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales-analyzer.transcription.api_key' => '']);
    }

    public function test_nothing_configured(): void
    {
        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertFalse($payload['transcription']['ready']);
        $this->assertSame('API key missing', $payload['transcription']['message']);
        $this->assertSame('not_configured', $payload['transcription']['source']);
        $this->assertFalse($payload['analysis']['ready']);
        $this->assertSame('API key missing', $payload['analysis']['message']);
        $this->assertFalse($payload['pipeline_ready']);
    }

    public function test_stt_ready_only(): void
    {
        $this->configureStt();

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertTrue($payload['transcription']['ready']);
        $this->assertSame('Ready', $payload['transcription']['message']);
        $this->assertSame('elevenlabs', $payload['transcription']['provider']);
        $this->assertSame('scribe_v2', $payload['transcription']['model']);
        $this->assertFalse($payload['analysis']['ready']);
        $this->assertFalse($payload['pipeline_ready']);
    }

    public function test_ai_ready_only(): void
    {
        $this->activateOpenAi();

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertFalse($payload['transcription']['ready']);
        $this->assertTrue($payload['analysis']['ready']);
        $this->assertSame('openai', $payload['analysis']['provider']);
        $this->assertSame('gpt-4o-mini', $payload['analysis']['model']);
        $this->assertFalse($payload['pipeline_ready']);
    }

    public function test_both_ready(): void
    {
        $this->configureStt();
        $this->activateOpenAi();

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertTrue($payload['pipeline_ready']);
        $this->assertTrue($payload['transcription']['ready']);
        $this->assertTrue($payload['analysis']['ready']);
    }

    public function test_failed_connection(): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key',
            'active_model' => 'scribe_v2',
            'is_active' => true,
            'is_connected' => false,
            'last_error' => 'Transcription credentials were rejected.',
        ])->save();

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertFalse($payload['transcription']['ready']);
        $this->assertSame('Connection failed', $payload['transcription']['message']);
        $this->assertSame('database', $payload['transcription']['source']);
        $this->assertFalse($payload['pipeline_ready']);
    }

    public function test_env_configured_stt(): void
    {
        config(['sales-analyzer.transcription.api_key' => 'env-fallback-key']);

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertTrue($payload['transcription']['ready']);
        $this->assertSame('environment', $payload['transcription']['source']);
        $this->assertSame('Ready', $payload['transcription']['message']);
        $this->assertFalse($payload['pipeline_ready']);
        $this->assertStringNotContainsString('env-fallback-key', json_encode($payload));
    }

    public function test_connection_not_checked(): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key',
            'active_model' => 'scribe_v2',
            'is_active' => true,
            'is_connected' => false,
            'last_error' => null,
        ])->save();

        $payload = app(AnalysisPipelineHealth::class)->payload();

        $this->assertFalse($payload['transcription']['ready']);
        $this->assertSame('Connection not checked', $payload['transcription']['message']);
    }

    private function configureStt(): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key',
            'active_model' => 'scribe_v2',
            'is_active' => true,
            'is_connected' => true,
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
        ]);
    }
}
