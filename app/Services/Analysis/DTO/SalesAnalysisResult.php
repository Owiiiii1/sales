<?php

namespace App\Services\Analysis\DTO;

class SalesAnalysisResult
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $scorecardSnapshot
     * @param  array<string, mixed>|null  $contextSnapshot
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $schemaVersion,
        public int $overallScore,
        public string $summary,
        public array $payload,
        public bool $companyContextUsed = false,
        public ?int $companyScorecardScore = null,
        public ?string $companyContextHash = null,
        public ?int $scorecardId = null,
        public ?array $scorecardSnapshot = null,
        public ?array $contextSnapshot = null,
    ) {}
}
