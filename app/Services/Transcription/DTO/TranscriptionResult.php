<?php

namespace App\Services\Transcription\DTO;

class TranscriptionResult
{
    /**
     * @param  array<int, TranscriptionSegment>  $segments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $provider,
        public string $model,
        public ?string $language,
        public string $text,
        public ?float $duration,
        public ?float $confidence,
        public ?string $requestId,
        public array $segments,
        public array $metadata = [],
    ) {}
}
