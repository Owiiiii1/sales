<?php

namespace App\Services\Analysis\DTO;

class AnalysisContext
{
    public function __construct(
        public ?string $language = null,
        public ?string $companyName = null,
    ) {}
}
