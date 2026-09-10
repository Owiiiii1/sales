<?php

namespace App\Services\Analytics;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsFilter
{
    public const PERIODS = [
        'last_7',
        'last_30',
        'last_90',
        'this_month',
        'previous_month',
        'all_time',
        'custom',
    ];

    public function __construct(
        public string $period,
        public ?CarbonInterface $from,
        public ?CarbonInterface $to,
        public ?int $companyId,
        public ?int $employeeId,
        public bool $employeeMismatch = false,
    ) {}

    public static function fromRequest(Request $request, ?int $companyId = null, ?int $employeeId = null): self
    {
        $period = (string) $request->query('period', config('sales-analyzer.analytics.default_period', 'last_30'));
        if (! in_array($period, self::PERIODS, true)) {
            $period = 'last_30';
        }

        $companyId = $companyId ?? self::nullableInt($request->query('company_id'));
        $employeeId = $employeeId ?? self::nullableInt($request->query('employee_id'));
        $mismatch = false;

        if ($employeeId !== null) {
            $employee = Employee::query()->find($employeeId);
            if ($employee === null) {
                $employeeId = null;
            } elseif ($companyId !== null && $employee->company_id !== $companyId) {
                $employeeId = null;
                $mismatch = true;
            }
        }

        [$from, $to] = self::range($period, $request);

        return new self($period, $from, $to, $companyId, $employeeId, $mismatch);
    }

    /**
     * @return array{0:?CarbonInterface, 1:?CarbonInterface}
     */
    private static function range(string $period, Request $request): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        return match ($period) {
            'last_7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'previous_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'custom' => self::customRange($request, $tz, $now),
            default => [null, null],
        };
    }

    /**
     * @return array{0:?CarbonInterface, 1:?CarbonInterface}
     */
    private static function customRange(Request $request, string $tz, CarbonInterface $now): array
    {
        $fromRaw = $request->query('from');
        $toRaw = $request->query('to');

        $from = is_string($fromRaw) && $fromRaw !== '' ? Carbon::parse($fromRaw, $tz)->startOfDay() : $now->copy()->subDays(29)->startOfDay();
        $to = is_string($toRaw) && $toRaw !== '' ? Carbon::parse($toRaw, $tz)->endOfDay() : $now->copy()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    public function previous(): ?self
    {
        if ($this->from === null || $this->to === null) {
            return null;
        }

        $days = $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
        $prevTo = $this->from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return new self(
            period: 'custom',
            from: $prevFrom,
            to: $prevTo,
            companyId: $this->companyId,
            employeeId: $this->employeeId,
        );
    }

    public function grouping(): string
    {
        if ($this->from === null || $this->to === null) {
            return 'week';
        }

        $days = $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;

        return $days <= 45 ? 'day' : 'week';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'from' => $this->from?->toIso8601String(),
            'to' => $this->to?->toIso8601String(),
            'from_date' => $this->from?->toDateString(),
            'to_date' => $this->to?->toDateString(),
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_mismatch' => $this->employeeMismatch,
            'timezone' => config('app.timezone'),
            'grouping' => $this->grouping(),
            'presets' => self::PERIODS,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
