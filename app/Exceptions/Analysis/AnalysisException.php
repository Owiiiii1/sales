<?php

namespace App\Exceptions\Analysis;

use RuntimeException;

class AnalysisException extends RuntimeException
{
    public function publicMessage(): string
    {
        return 'Analysis failed. Please try again.';
    }
}
