<?php

namespace App\Exceptions\Transcription;

use RuntimeException;

class TranscriptionException extends RuntimeException
{
    public function publicMessage(): string
    {
        return 'Transcription failed. Please try again.';
    }
}
