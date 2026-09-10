<?php

namespace App\Support;

use App\Models\Company;

class CompanyKnowledgeCompleteness
{
    /**
     * @return array{percent:int, items:array<string, bool>}
     */
    public function for(Company $company): array
    {
        $company->loadMissing(['profile', 'offerings', 'salesScripts', 'scorecards.criteria']);

        $items = [
            'target_audience' => filled($company->profile?->target_audience),
            'usp' => filled($company->profile?->usp),
            'customer_pains' => filled($company->profile?->customer_pains),
            'offerings' => $company->offerings->where('is_active', true)->isNotEmpty(),
            'script' => $company->salesScripts->where('is_active', true)->isNotEmpty(),
            'scorecard' => $company->scorecards->contains(
                fn ($scorecard): bool => $scorecard->is_active && $scorecard->criteria->where('is_active', true)->isNotEmpty()
            ),
        ];

        $filled = count(array_filter($items));

        return [
            'percent' => (int) round(($filled / count($items)) * 100),
            'items' => $items,
        ];
    }
}
