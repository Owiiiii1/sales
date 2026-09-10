<?php

namespace App\Services\Transcription\DTO;

class TranscriptionCredentials
{
    public function __construct(
        public string $provider,
        public string $label,
        public string $model,
        public string $apiKey,
        public string $source,
        public bool $connected,
        public bool $active,
    ) {}

    public function isReady(): bool
    {
        if ($this->apiKey === '' || $this->model === '') {
            return false;
        }

        if ($this->source === 'environment') {
            return true;
        }

        return $this->connected && $this->active;
    }
}
