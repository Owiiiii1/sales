<?php

namespace App\Services\Analytics;

use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Services\Analysis\SalesAnalysisSchema;
use App\Support\ScoreBand;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnalyticsAggregator
{
    /**
     * @param  Collection<int, Call>  $calls
     * @return array<string, mixed>
     */
    public function summarize(Collection $calls, AnalyticsFilter $filter, ?Collection $previousCalls = null): array
    {
        $analyses = $calls
            ->map(fn (Call $call) => $call->analysis)
            ->filter()
            ->values();

        $kpis = $this->kpis($calls);
        $previousKpis = $previousCalls !== null ? $this->kpis($previousCalls) : null;

        return [
            'kpis' => $kpis,
            'trends' => $this->trends($calls, $filter),
            'comparison' => $this->comparison($kpis, $previousKpis),
            'sections' => $this->sections($analyses),
            'lowest_sections' => $this->lowestSections($analyses),
            'outcomes' => $this->distribution($analyses, 'call_outcome', SalesAnalysisSchema::OUTCOMES),
            'intents' => $this->distribution($analyses, 'customer_intent', SalesAnalysisSchema::INTENTS),
            'scorecard' => $this->scorecard($analyses),
            'mandatory_questions' => $this->mandatoryQuestions($analyses),
            'violations' => $this->violations($calls),
            'recent_strengths' => $this->recentFindings($analyses, 'strengths'),
            'recent_weaknesses' => $this->recentFindings($analyses, 'weaknesses'),
            'recent_calls' => $this->recentCalls($calls),
        ];
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @return array<string, mixed>
     */
    public function kpis(Collection $calls): array
    {
        $total = $calls->count();
        $analyzed = $calls->where('status', 'completed')->count();
        $failed = $calls->where('status', 'failed')->count();
        $pending = $total - $analyzed - $failed;
        $finished = $analyzed + $failed;

        $sales = $calls
            ->map(fn (Call $call) => $call->analysis?->overall_score)
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (int) $score)
            ->values();

        $scorecard = $calls
            ->map(fn (Call $call) => $call->analysis?->company_scorecard_score)
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (int) $score)
            ->values();

        $durations = $calls
            ->pluck('duration_seconds')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->values();

        $averageSales = $this->average($sales);
        $averageScorecard = $this->average($scorecard);

        return [
            'total_calls' => $total,
            'analyzed_calls' => $analyzed,
            'failed_calls' => $failed,
            'pending_calls' => $pending,
            'completion_rate' => $this->rate($analyzed, $total),
            'failed_rate' => $this->rate($failed, $total),
            'pending_rate' => $this->rate($pending, $total),
            'analysis_success_rate' => $this->rate($analyzed, $finished),
            'average_sales_score' => $averageSales,
            'average_sales_score_band' => ScoreBand::for($averageSales),
            'average_company_score' => $averageScorecard,
            'average_company_score_band' => ScoreBand::for($averageScorecard),
            'active_employees' => $calls->pluck('employee_id')->filter()->unique()->count(),
            'average_duration_seconds' => $durations->isEmpty() ? null : (int) round($durations->avg()),
        ];
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @return array<string, mixed>
     */
    public function trends(Collection $calls, AnalyticsFilter $filter): array
    {
        $group = $filter->grouping();
        $buckets = $this->bucketKeys($filter);

        $callCounts = [];
        $sales = [];
        $scorecards = [];

        foreach ($calls as $call) {
            $key = $this->bucketKey($call->analyticsAt(), $group);
            $callCounts[$key] = ($callCounts[$key] ?? 0) + 1;

            if ($call->analysis?->overall_score !== null) {
                $sales[$key][] = (int) $call->analysis->overall_score;
            }

            if ($call->analysis?->company_scorecard_score !== null) {
                $scorecards[$key][] = (int) $call->analysis->company_scorecard_score;
            }
        }

        $keys = $buckets !== [] ? $buckets : array_keys($callCounts);
        sort($keys);

        $callsSeries = [];
        $salesSeries = [];
        $scorecardSeries = [];

        foreach ($keys as $key) {
            $label = $this->bucketLabel($key, $group);
            $callsSeries[] = ['key' => $key, 'label' => $label, 'value' => $callCounts[$key] ?? 0];
            $salesSeries[] = ['key' => $key, 'label' => $label, 'value' => $this->average(collect($sales[$key] ?? []))];
            $scorecardSeries[] = ['key' => $key, 'label' => $label, 'value' => $this->average(collect($scorecards[$key] ?? []))];
        }

        return [
            'group' => $group,
            'calls' => $callsSeries,
            'sales' => $salesSeries,
            'scorecard' => $scorecardSeries,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>|null  $previous
     * @return array<string, mixed>
     */
    public function comparison(array $current, ?array $previous): array
    {
        return [
            'average_sales_score' => $this->trend($current['average_sales_score'] ?? null, $previous['average_sales_score'] ?? null),
            'average_company_score' => $this->trend($current['average_company_score'] ?? null, $previous['average_company_score'] ?? null),
            'total_calls' => $this->trend($current['total_calls'] ?? null, $previous['total_calls'] ?? null, allowZeroPrevious: false),
        ];
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @return array<int, array<string, mixed>>
     */
    public function sections(Collection $analyses): array
    {
        $rows = [];

        foreach (SalesAnalysisSchema::SECTION_KEYS as $key) {
            $all = 0;
            $scores = [];

            foreach ($analyses as $analysis) {
                $section = $analysis->result['sections'][$key] ?? null;
                if (! is_array($section)) {
                    continue;
                }

                $all++;
                if (($section['applicable'] ?? true) !== true) {
                    continue;
                }
                if (! isset($section['score']) || $section['score'] === null) {
                    continue;
                }

                $scores[] = (int) $section['score'];
            }

            $average = $this->average(collect($scores));
            $rows[] = [
                'key' => $key,
                'title' => SalesAnalysisSchema::SECTION_TITLES[$key],
                'average_score' => $average,
                'band' => ScoreBand::for($average),
                'call_count' => $all,
                'applicable_count' => count($scores),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @return array<int, array<string, mixed>>
     */
    public function lowestSections(Collection $analyses): array
    {
        return collect($this->sections($analyses))
            ->filter(fn (array $row) => $row['average_score'] !== null)
            ->sortBy('average_score')
            ->values()
            ->take(3)
            ->all();
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @param  array<int, string>  $allowed
     * @return array<int, array{key:string, count:int}>
     */
    public function distribution(Collection $analyses, string $field, array $allowed): array
    {
        $counts = array_fill_keys($allowed, 0);

        foreach ($analyses as $analysis) {
            $value = $analysis->result[$field] ?? 'unknown';
            if (! in_array($value, $allowed, true)) {
                $value = 'unknown';
            }
            $counts[$value]++;
        }

        return collect($counts)->map(fn (int $count, string $key): array => [
            'key' => $key,
            'count' => $count,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @return array<int, array<string, mixed>>
     */
    public function scorecard(Collection $analyses): array
    {
        $byKey = [];

        foreach ($analyses as $analysis) {
            $snapshot = collect($analysis->scorecard_snapshot['criteria'] ?? [])->keyBy('key');
            $results = $analysis->result['company_specific']['scorecard']['criteria'] ?? [];

            if (! is_array($results)) {
                continue;
            }

            foreach ($results as $row) {
                if (! is_array($row) || ! isset($row['key'])) {
                    continue;
                }

                $key = (string) $row['key'];
                $snap = $snapshot->get($key);
                if (! is_array($snap)) {
                    continue;
                }

                $byKey[$key]['name'] = $snap['name'] ?? $key;
                $byKey[$key]['weight_sum'] = ($byKey[$key]['weight_sum'] ?? 0) + (float) ($snap['weight'] ?? 0);
                $byKey[$key]['weight_n'] = ($byKey[$key]['weight_n'] ?? 0) + 1;
                $byKey[$key]['critical'] = ($byKey[$key]['critical'] ?? 0) + (($row['critical_failure'] ?? false) ? 1 : 0);

                if (($row['applicable'] ?? true) !== true || ! isset($row['score']) || $row['score'] === null) {
                    $byKey[$key]['applicable'] = $byKey[$key]['applicable'] ?? 0;

                    continue;
                }

                $max = max(1, (int) ($row['max_score'] ?? $snap['max_score'] ?? 100));
                $byKey[$key]['normalized'][] = ((int) $row['score'] / $max) * 100;
                $byKey[$key]['applicable'] = ($byKey[$key]['applicable'] ?? 0) + 1;
            }
        }

        return collect($byKey)->map(function (array $row, string $key): array {
            $average = isset($row['normalized']) ? $this->average(collect($row['normalized'])) : null;

            return [
                'key' => $key,
                'name' => $row['name'],
                'weight' => isset($row['weight_n']) ? round($row['weight_sum'] / $row['weight_n'], 1) : null,
                'average_score' => $average,
                'band' => ScoreBand::for($average),
                'applicable_count' => $row['applicable'] ?? 0,
                'critical_failure_count' => $row['critical'] ?? 0,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @return array<string, mixed>
     */
    public function mandatoryQuestions(Collection $analyses): array
    {
        $asked = [];
        $missed = [];

        foreach ($analyses as $analysis) {
            $block = $analysis->result['company_specific']['mandatory_questions'] ?? null;
            if (! is_array($block)) {
                continue;
            }

            foreach ($block['asked'] ?? [] as $text) {
                if (! is_string($text) || $text === '') {
                    continue;
                }
                $asked[$text] = ($asked[$text] ?? 0) + 1;
            }

            foreach ($block['missed'] ?? [] as $text) {
                if (! is_string($text) || $text === '') {
                    continue;
                }
                $missed[$text] = ($missed[$text] ?? 0) + 1;
            }
        }

        $topMissed = collect($missed)
            ->map(fn (int $count, string $text): array => ['text' => $text, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->take((int) config('sales-analyzer.analytics.top_missed_questions', 10))
            ->all();

        return [
            'asked_count' => array_sum($asked),
            'missed_count' => array_sum($missed),
            'top_missed' => $topMissed,
        ];
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @return array<string, mixed>
     */
    public function violations(Collection $calls): array
    {
        $limit = (int) config('sales-analyzer.analytics.latest_violations', 10);
        $callsWith = 0;
        $count = 0;
        $latest = [];

        foreach ($calls as $call) {
            $items = $call->analysis?->result['company_specific']['forbidden_claims']['violations'] ?? [];
            if (! is_array($items) || $items === []) {
                continue;
            }

            $texts = [];
            foreach ($items as $item) {
                if (is_string($item) && $item !== '') {
                    $texts[] = $item;
                } elseif (is_array($item) && filled($item['text'] ?? null)) {
                    $texts[] = (string) $item['text'];
                }
            }

            if ($texts === []) {
                continue;
            }

            $callsWith++;
            $count += count($texts);
            $latest[] = [
                'call_id' => $call->id,
                'date' => $call->analyticsAt()->toIso8601String(),
                'texts' => $texts,
                'show_url' => route('calls.show', $call),
            ];
        }

        usort($latest, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return [
            'calls_with_violations' => $callsWith,
            'violation_count' => $count,
            'latest' => array_slice($latest, 0, $limit),
        ];
    }

    /**
     * @param  Collection<int, SalesAnalysis>  $analyses
     * @return array<int, string>
     */
    public function recentFindings(Collection $analyses, string $field): array
    {
        $limit = (int) config('sales-analyzer.analytics.recent_findings', 8);

        $texts = [];
        foreach ($analyses->sortByDesc(fn (SalesAnalysis $analysis) => optional($analysis->completed_at)?->timestamp ?? 0) as $analysis) {
            foreach ($analysis->result[$field] ?? [] as $item) {
                $text = is_string($item) ? $item : (is_array($item) ? (string) ($item['text'] ?? '') : '');
                if ($text === '' || in_array($text, $texts, true)) {
                    continue;
                }
                $texts[] = $text;
                if (count($texts) >= $limit) {
                    return $texts;
                }
            }
        }

        return $texts;
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @return array<int, array<string, mixed>>
     */
    public function recentCalls(Collection $calls): array
    {
        $limit = (int) config('sales-analyzer.analytics.recent_calls', 20);

        return $calls->take($limit)->map(function (Call $call): array {
            $call->loadMissing(['company:id,name', 'employee:id,first_name,last_name']);

            return [
                'id' => $call->id,
                'status' => $call->status,
                'company_name' => $call->company?->name,
                'employee_name' => $call->employee?->full_name,
                'overall_score' => $call->analysis?->overall_score,
                'company_scorecard_score' => $call->analysis?->company_scorecard_score,
                'duration_seconds' => $call->duration_seconds,
                'date' => $call->analyticsAt()->toIso8601String(),
                'show_url' => route('calls.show', $call),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @param  Collection<int, Call>  $previous
     * @return array<int, array<string, mixed>>
     */
    public function employeeRows(Collection $employees, Collection $calls, Collection $previous): array
    {
        return $employees->map(function ($employee) use ($calls, $previous): array {
            $current = $calls->where('employee_id', $employee->id);
            $prior = $previous->where('employee_id', $employee->id);
            $kpis = $this->kpis($current);
            $priorKpis = $this->kpis($prior);

            return [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'show_url' => route('employees.show', $employee),
                'calls' => $kpis['total_calls'],
                'analyzed' => $kpis['analyzed_calls'],
                'average_sales_score' => $kpis['average_sales_score'],
                'average_company_score' => $kpis['average_company_score'],
                'average_duration_seconds' => $kpis['average_duration_seconds'],
                'failed' => $kpis['failed_calls'],
                'trend' => $this->trend($kpis['average_sales_score'], $priorKpis['average_sales_score']),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, float|int>  $values
     */
    private function average(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 1);
    }

    private function rate(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return round(($part / $whole) * 100, 1);
    }

    /**
     * @return array{current:mixed, previous:mixed, percent:?float, direction:?string}
     */
    private function trend(mixed $current, mixed $previous, bool $allowZeroPrevious = true): array
    {
        $percent = null;
        $direction = null;

        if ($current !== null && $previous !== null && is_numeric($current) && is_numeric($previous)) {
            if ((float) $previous === 0.0 && ! $allowZeroPrevious) {
                $percent = null;
            } elseif ((float) $previous === 0.0) {
                $percent = null;
            } else {
                $percent = round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
                $direction = $percent > 0.5 ? 'up' : ($percent < -0.5 ? 'down' : 'flat');
            }
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'percent' => $percent,
            'direction' => $direction,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function bucketKeys(AnalyticsFilter $filter): array
    {
        if ($filter->from === null || $filter->to === null) {
            return [];
        }

        $group = $filter->grouping();
        $cursor = $filter->from->copy()->timezone((string) config('app.timezone'));
        $end = $filter->to->copy()->timezone((string) config('app.timezone'));
        $keys = [];

        while ($cursor->lte($end)) {
            $keys[] = $this->bucketKey($cursor, $group);
            $cursor = $group === 'day' ? $cursor->addDay() : $cursor->addWeek();
        }

        return array_values(array_unique($keys));
    }

    private function bucketKey(CarbonInterface $date, string $group): string
    {
        $local = $date->copy()->timezone((string) config('app.timezone'));

        return $group === 'week' ? $local->format('o-\WW') : $local->format('Y-m-d');
    }

    private function bucketLabel(string $key, string $group): string
    {
        if ($group === 'week') {
            $date = Carbon::now(config('app.timezone'))->setISODate((int) substr($key, 0, 4), (int) substr($key, -2));

            return $date->format('M j');
        }

        return Carbon::parse($key, config('app.timezone'))->format('M j');
    }
}
