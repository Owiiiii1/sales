<?php

namespace App\Services\Pipeline;

use App\Models\AiProviderSetting;
use App\Services\Ai\ActiveAiProvider;
use App\Services\Transcription\ActiveTranscriptionProvider;

class AnalysisPipelineHealth
{
    public function __construct(
        private ActiveTranscriptionProvider $transcription,
        private ActiveAiProvider $ai,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $transcription = $this->transcriptionStatus();
        $analysis = $this->analysisStatus();

        return [
            'transcription' => $transcription,
            'analysis' => $analysis,
            'pipeline_ready' => $transcription['ready'] && $analysis['ready'],
        ];
    }

    public function transcriptionReady(): bool
    {
        return $this->transcription->isReady();
    }

    public function analysisReady(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    private function transcriptionStatus(): array
    {
        $current = $this->transcription->current();
        $row = $this->transcription->row();

        if ($current === null) {
            return [
                'ready' => false,
                'provider' => ActiveTranscriptionProvider::PROVIDER,
                'model' => $row->active_model,
                'message' => __('API key missing'),
                'source' => 'not_configured',
            ];
        }

        if ($current->model === '') {
            return [
                'ready' => false,
                'provider' => $current->provider,
                'model' => null,
                'message' => __('Model missing'),
                'source' => $current->source,
            ];
        }

        if ($current->source === 'database' && ! $current->connected) {
            return [
                'ready' => false,
                'provider' => $current->provider,
                'model' => $current->model,
                'message' => filled($row->last_error) ? __('Connection failed') : __('Connection not checked'),
                'source' => 'database',
            ];
        }

        if ($current->source === 'database' && ! $current->active) {
            return [
                'ready' => false,
                'provider' => $current->provider,
                'model' => $current->model,
                'message' => __('Not active'),
                'source' => 'database',
            ];
        }

        return [
            'ready' => true,
            'provider' => $current->provider,
            'model' => $current->model,
            'message' => __('Ready'),
            'source' => $current->source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisStatus(): array
    {
        $active = $this->ai->current();

        if ($active !== null) {
            return [
                'ready' => true,
                'provider' => $active->provider,
                'model' => $active->active_model,
                'message' => __('Ready'),
            ];
        }

        $withKey = AiProviderSetting::query()->whereNotNull('api_key')->get();

        if ($withKey->isEmpty()) {
            return [
                'ready' => false,
                'provider' => null,
                'model' => null,
                'message' => __('API key missing'),
            ];
        }

        $connected = $withKey->firstWhere('is_connected', true);

        if ($connected === null) {
            return [
                'ready' => false,
                'provider' => $withKey->first()?->provider,
                'model' => $withKey->first()?->active_model,
                'message' => __('Connection failed'),
            ];
        }

        if (! filled($connected->active_model)) {
            return [
                'ready' => false,
                'provider' => $connected->provider,
                'model' => null,
                'message' => __('Model missing'),
            ];
        }

        return [
            'ready' => false,
            'provider' => $connected->provider,
            'model' => $connected->active_model,
            'message' => __('No active provider'),
        ];
    }
}
