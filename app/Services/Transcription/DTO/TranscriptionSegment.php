<?php

namespace App\Services\Transcription\DTO;

class TranscriptionSegment
{
    public function __construct(
        public int $speaker,
        public float $start,
        public float $end,
        public string $text,
        public ?float $confidence = null,
    ) {}
}
