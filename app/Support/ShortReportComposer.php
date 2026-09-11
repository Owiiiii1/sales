<?php

namespace App\Support;

class ShortReportComposer
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function from(array $report): array
    {
        $strengths = self::take($report['what_to_repeat'] ?? [], 3);
        if ($strengths === []) {
            $strengths = self::take($report['strengths'] ?? [], 3);
        }

        $problems = self::take($report['critical_mistakes'] ?? [], 3);
        if ($problems === []) {
            $problems = self::take($report['weaknesses'] ?? [], 3);
        }

        $actions = self::nextActions($report);

        return [
            'strengths' => $strengths,
            'problems' => $problems,
            'next_actions' => $actions,
            'scorecard' => self::scorecardSummary($report),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function take(mixed $items, int $limit): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        return array_values(array_slice(array_values($items), 0, $limit));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array{text:string}>
     */
    private static function nextActions(array $report): array
    {
        $actions = [];

        foreach ($report['coaching_priorities'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['practice'] ?? $item['skill'] ?? ''));
            if ($text === '') {
                continue;
            }
            $actions[] = ['text' => $text];
        }

        $playbook = $report['next_call_playbook']['during_call'] ?? [];
        if (is_array($playbook)) {
            foreach ($playbook as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $actions[] = ['text' => $line];
                }
            }
        }

        $best = $report['executive_summary']['best_next_action'] ?? null;
        if (is_string($best) && trim($best) !== '') {
            $actions[] = ['text' => $best];
        }

        $unique = [];
        $seen = [];
        foreach ($actions as $action) {
            $key = mb_strtolower($action['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $action;
        }

        return self::take($unique, 3);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{weakest: array<int, array<string, mixed>>, strongest: array<int, array<string, mixed>>}|null
     */
    private static function scorecardSummary(array $report): ?array
    {
        $criteria = $report['company_specific']['scorecard']['criteria'] ?? [];
        if (! is_array($criteria) || ($report['company_context_used'] ?? false) !== true) {
            return null;
        }

        $applicable = array_values(array_filter(
            $criteria,
            fn (mixed $row): bool => is_array($row) && ($row['applicable'] ?? true) !== false && isset($row['score']),
        ));

        if ($applicable === []) {
            return null;
        }

        $ratio = static function (array $row): float {
            $max = max(1, (int) ($row['max_score'] ?? 100));

            return ((int) $row['score']) / $max;
        };

        usort($applicable, fn (array $a, array $b): int => $ratio($a) <=> $ratio($b));

        $weakest = array_slice($applicable, 0, 3);
        $strongest = array_reverse(array_slice($applicable, -2));

        return [
            'weakest' => $weakest,
            'strongest' => $strongest,
        ];
    }
}
