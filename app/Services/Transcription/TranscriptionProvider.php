<?php

namespace App\Services\Transcription;

use App\Models\Call;
use App\Services\Transcription\DTO\TranscriptionResult;

interface TranscriptionProvider
{
    public function transcribe(Call $call): TranscriptionResult;
}
