<?php

namespace App\Services\Analytics;

class DashboardAnalyticsService
{
    public function __construct(
        private AnalyticsQuery $query,
        private AnalyticsAggregator $aggregator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(AnalyticsFilter $filter): array
    {
        $companyBound = $filter->companyId !== null || $filter->employeeId !== null;
        $calls = $this->query->load($filter, $companyBound);
        $previousFilter = $filter->previous();
        $previous = $previousFilter !== null
            ? $this->query->load($previousFilter, $companyBound)
            : collect();

        $summary = $this->aggregator->summarize($calls, $filter, $previous);
        $summary['kpis']['public_analyses'] = $this->query->publicCompletedCount($filter);
        $summary['kpis']['calls_this_period'] = $summary['kpis']['total_calls'];

        return [
            'filters' => $filter->toArray(),
            'companies' => $this->query->companies(),
            'employees' => $this->query->employees($filter->companyId),
            'analytics' => $summary,
        ];
    }
}
