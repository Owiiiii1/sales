<?php

namespace App\Services\Analysis\DTO;

class AnalysisContext
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $scorecardSnapshot
     */
    public function __construct(
        public ?string $language = null,
        public ?string $reportLanguage = null,
        public ?string $companyName = null,
        public bool $companyContextUsed = false,
        public ?int $companyId = null,
        public ?string $companyContextHash = null,
        public ?int $scorecardId = null,
        public array $snapshot = [],
        public ?array $scorecardSnapshot = null,
        public string $companyContextText = '',
        public bool $contextTruncated = false,
    ) {}
}
