<?php

namespace App\Services\Analytics;

use App\Models\Employee;

class EmployeeAnalyticsService
{
    public function __construct(
        private AnalyticsQuery $query,
        private AnalyticsAggregator $aggregator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Employee $employee, AnalyticsFilter $filter): array
    {
        $scoped = new AnalyticsFilter(
            period: $filter->period,
            from: $filter->from,
            to: $filter->to,
            companyId: $employee->company_id,
            employeeId: $employee->id,
        );

        $calls = $this->query->load($scoped, true);
        $previousFilter = $scoped->previous();
        $previous = $previousFilter !== null ? $this->query->load($previousFilter, true) : collect();

        return $this->aggregator->summarize($calls, $scoped, $previous);
    }
}
