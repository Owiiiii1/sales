<?php

namespace App\Exceptions\Analysis;

class TransientAnalysisException extends AnalysisException
{
    public function publicMessage(): string
    {
        return 'Analysis is temporarily unavailable. Please try again.';
    }
}
