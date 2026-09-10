<?php

namespace App\Services\Analytics;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsQuery
{
    /**
     * @return Builder<Call>
     */
    public function calls(AnalyticsFilter $filter, bool $companyBound = false): Builder
    {
        $query = Call::query()->select([
            'id',
            'company_id',
            'employee_id',
            'status',
            'duration_seconds',
            'recorded_at',
            'created_at',
            'original_filename',
        ]);

        if ($companyBound) {
            $query->whereNotNull('company_id');
        }

        if ($filter->companyId !== null) {
            $query->where('company_id', $filter->companyId);
        }

        if ($filter->employeeId !== null) {
            $query->where('employee_id', $filter->employeeId);
        }

        if ($filter->from !== null && $filter->to !== null) {
            $query->whereRaw('COALESCE(recorded_at, created_at) BETWEEN ? AND ?', [
                $filter->from->toDateTimeString(),
                $filter->to->toDateTimeString(),
            ]);
        }

        return $query;
    }

    /**
     * @return Collection<int, Call>
     */
    public function load(AnalyticsFilter $filter, bool $companyBound = false): Collection
    {
        return $this->calls($filter, $companyBound)
            ->with([
                'analysis' => function ($query): void {
                    $query->select([
                        'id',
                        'call_id',
                        'overall_score',
                        'company_scorecard_score',
                        'result',
                        'scorecard_snapshot',
                        'completed_at',
                    ]);
                },
                'company:id,name',
                'employee:id,first_name,last_name',
            ])
            ->orderByRaw('COALESCE(recorded_at, created_at) desc')
            ->get();
    }

    public function publicCompletedCount(AnalyticsFilter $filter): int
    {
        if ($filter->companyId !== null || $filter->employeeId !== null) {
            return 0;
        }

        $public = new AnalyticsFilter(
            period: $filter->period,
            from: $filter->from,
            to: $filter->to,
            companyId: null,
            employeeId: null,
        );

        return $this->calls($public)
            ->whereNull('company_id')
            ->where('status', 'completed')
            ->count();
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function companies(): array
    {
        return Company::query()->orderBy('name')->get(['id', 'name'])->map(fn (Company $company): array => [
            'id' => $company->id,
            'name' => $company->name,
        ])->all();
    }

    /**
     * @return array<int, array{id:int, company_id:int, full_name:string}>
     */
    public function employees(?int $companyId = null): array
    {
        return Employee::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('first_name')
            ->get(['id', 'company_id', 'first_name', 'last_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'full_name' => $employee->full_name,
            ])
            ->all();
    }
}
