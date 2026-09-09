<?php

namespace App\Exceptions\Analysis;

class PermanentAnalysisException extends AnalysisException
{
    public function __construct(
        string $message,
        private readonly string $publicMessage = 'Analysis failed. Please try again.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
