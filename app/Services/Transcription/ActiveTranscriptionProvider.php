<?php

namespace App\Services\Transcription;

use App\Models\TranscriptionProviderSetting;
use App\Services\Transcription\DTO\TranscriptionCredentials;

class ActiveTranscriptionProvider
{
    public const PROVIDER = 'elevenlabs';

    public function row(): TranscriptionProviderSetting
    {
        return TranscriptionProviderSetting::query()->firstOrCreate(
            ['provider' => self::PROVIDER],
            [
                'label' => 'ElevenLabs',
                'is_active' => true,
                'active_model' => (string) config('sales-analyzer.transcription.model', 'scribe_v2'),
                'available_models' => config('sales-analyzer.transcription.supported_models', [
                    ['id' => 'scribe_v2', 'name' => 'Scribe v2'],
                ]),
            ],
        );
    }

    public function current(): ?TranscriptionCredentials
    {
        $row = $this->row();
        $dbKey = filled($row->api_key) ? trim((string) $row->api_key) : '';
        $envKey = trim((string) config('sales-analyzer.transcription.api_key'));
        $model = filled($row->active_model)
            ? (string) $row->active_model
            : (string) config('sales-analyzer.transcription.model', 'scribe_v2');

        if ($dbKey !== '') {
            return new TranscriptionCredentials(
                provider: self::PROVIDER,
                label: $row->label ?: 'ElevenLabs',
                model: $model,
                apiKey: $dbKey,
                source: 'database',
                connected: (bool) $row->is_connected,
                active: (bool) $row->is_active,
            );
        }

        if ($envKey !== '') {
            return new TranscriptionCredentials(
                provider: self::PROVIDER,
                label: 'ElevenLabs',
                model: $model !== '' ? $model : (string) config('sales-analyzer.transcription.model', 'scribe_v2'),
                apiKey: $envKey,
                source: 'environment',
                connected: true,
                active: true,
            );
        }

        return null;
    }

    public function isReady(): bool
    {
        return $this->current()?->isReady() === true;
    }

    public function source(): string
    {
        return $this->current()?->source ?? 'not_configured';
    }
}
