<?php

namespace App\Exceptions\Transcription;

class PermanentTranscriptionException extends TranscriptionException
{
    public function __construct(
        string $message,
        private readonly string $publicMessage = 'Transcription failed. Please try again.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
