<?php

namespace App\Services\Analytics;

use App\Models\Company;

class CompanyAnalyticsService
{
    public function __construct(
        private AnalyticsQuery $query,
        private AnalyticsAggregator $aggregator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Company $company, AnalyticsFilter $filter): array
    {
        $scoped = new AnalyticsFilter(
            period: $filter->period,
            from: $filter->from,
            to: $filter->to,
            companyId: $company->id,
            employeeId: $filter->employeeId,
        );

        $calls = $this->query->load($scoped, true);
        $previousFilter = $scoped->previous();
        $previous = $previousFilter !== null ? $this->query->load($previousFilter, true) : collect();
        $summary = $this->aggregator->summarize($calls, $scoped, $previous);
        $summary['kpis']['employees_count'] = $company->employees()->count();
        $summary['employees'] = $this->aggregator->employeeRows(
            $company->employees()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            $calls,
            $previous,
        );

        return $summary;
    }
}
