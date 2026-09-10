<?php

namespace Tests\Unit;

use App\Models\TranscriptionProviderSetting;
use App\Services\Transcription\ActiveTranscriptionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveTranscriptionProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sales-analyzer.transcription.api_key' => '',
            'sales-analyzer.transcription.model' => 'scribe_v2',
        ]);
    }

    public function test_db_config_is_preferred_over_env(): void
    {
        config(['sales-analyzer.transcription.api_key' => 'env-key']);
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key',
            'active_model' => 'scribe_v2',
            'is_connected' => true,
            'is_active' => true,
        ])->save();

        $current = app(ActiveTranscriptionProvider::class)->current();

        $this->assertSame('db-key', $current->apiKey);
        $this->assertSame('database', $current->source);
        $this->assertTrue($current->isReady());
    }

    public function test_env_fallback_when_db_key_is_empty(): void
    {
        config(['sales-analyzer.transcription.api_key' => 'env-key']);

        $current = app(ActiveTranscriptionProvider::class)->current();

        $this->assertSame('env-key', $current->apiKey);
        $this->assertSame('environment', $current->source);
        $this->assertSame('scribe_v2', $current->model);
        $this->assertTrue($current->isReady());
        $this->assertSame('environment', app(ActiveTranscriptionProvider::class)->source());
    }

    public function test_no_config(): void
    {
        $this->assertNull(app(ActiveTranscriptionProvider::class)->current());
        $this->assertFalse(app(ActiveTranscriptionProvider::class)->isReady());
        $this->assertSame('not_configured', app(ActiveTranscriptionProvider::class)->source());
    }

    public function test_model_falls_back_to_config(): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key',
            'active_model' => null,
            'is_connected' => true,
            'is_active' => true,
        ])->save();

        $current = app(ActiveTranscriptionProvider::class)->current();

        $this->assertSame('scribe_v2', $current->model);
    }

    public function test_decrypted_key_is_not_in_model_array(): void
    {
        $row = app(ActiveTranscriptionProvider::class)->row();
        $row->fill([
            'api_key' => 'db-key-hidden',
            'is_connected' => true,
            'is_active' => true,
        ])->save();

        $array = TranscriptionProviderSetting::query()->first()->toArray();

        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertSame('db-key-hidden', TranscriptionProviderSetting::query()->first()->api_key);
    }
}
