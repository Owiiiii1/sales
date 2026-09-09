<?php

namespace App\Services\Analysis\DTO;

class SalesAnalysisResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $schemaVersion,
        public int $overallScore,
        public string $summary,
        public array $payload,
    ) {}
}
