<?php

namespace App\Services\Analysis;

use App\Models\Transcript;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\DTO\SalesAnalysisResult;

interface SalesAnalysisProvider
{
    public function isConfigured(): bool;

    public function analyze(Transcript $transcript, AnalysisContext $context): SalesAnalysisResult;
}
