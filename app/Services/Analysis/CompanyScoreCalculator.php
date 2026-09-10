<?php

namespace App\Services\Analysis;

class CompanyScoreCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $criteriaResults
     * @param  array<string, mixed>  $scorecardSnapshot
     */
    public function total(array $criteriaResults, array $scorecardSnapshot): ?int
    {
        $byKey = [];
        foreach ($criteriaResults as $result) {
            if (! is_array($result) || ! isset($result['key'])) {
                continue;
            }
            $byKey[(string) $result['key']] = $result;
        }

        $weighted = 0.0;
        $weights = 0.0;

        foreach ($scorecardSnapshot['criteria'] ?? [] as $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $key = (string) ($criterion['key'] ?? '');
            $result = $byKey[$key] ?? null;

            if ($result === null || ($result['applicable'] ?? true) !== true) {
                continue;
            }

            $max = max(1, (int) ($criterion['max_score'] ?? 100));
            $score = (int) ($result['score'] ?? 0);
            $weight = (float) ($criterion['weight'] ?? 0);

            $weighted += ($score / $max) * $weight;
            $weights += $weight;
        }

        if ($weights <= 0) {
            return null;
        }

        return (int) round(($weighted / $weights) * 100);
    }
}
