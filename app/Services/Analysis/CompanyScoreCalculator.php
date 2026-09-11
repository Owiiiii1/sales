<?php

namespace App\Services\Analysis;

use App\Models\CompanyScorecardCap;

class CompanyScoreCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $criteriaResults
     * @param  array<string, mixed>  $scorecardSnapshot
     */
    public function total(array $criteriaResults, array $scorecardSnapshot, array $companySpecific = []): ?int
    {
        return $this->scored($criteriaResults, $scorecardSnapshot, $companySpecific)['total_score'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteriaResults
     * @param  array<string, mixed>  $scorecardSnapshot
     * @param  array<string, mixed>  $companySpecific
     * @return array{weighted_score:?int, total_score:?int, triggered_caps: array<int, array<string, mixed>>}
     */
    public function scored(array $criteriaResults, array $scorecardSnapshot, array $companySpecific = []): array
    {
        $weighted = $this->weighted($criteriaResults, $scorecardSnapshot);

        if ($weighted === null) {
            return [
                'weighted_score' => null,
                'total_score' => null,
                'triggered_caps' => [],
            ];
        }

        $triggered = $this->triggeredCaps($scorecardSnapshot['caps'] ?? [], $criteriaResults, $companySpecific);
        $final = $weighted;

        if ($triggered !== []) {
            $lowest = min(array_map(fn (array $cap): int => (int) $cap['max_total_score'], $triggered));
            $final = min($weighted, $lowest);
        }

        return [
            'weighted_score' => $weighted,
            'total_score' => $final,
            'triggered_caps' => $triggered,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteriaResults
     * @param  array<string, mixed>  $scorecardSnapshot
     */
    private function weighted(array $criteriaResults, array $scorecardSnapshot): ?int
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

    /**
     * @param  array<int, array<string, mixed>>  $criteriaResults
     * @param  array<string, mixed>  $companySpecific
     * @return array<int, array<string, mixed>>
     */
    private function triggeredCaps(mixed $caps, array $criteriaResults, array $companySpecific): array
    {
        if (! is_array($caps)) {
            return [];
        }

        $byKey = [];
        foreach ($criteriaResults as $result) {
            if (is_array($result) && isset($result['key'])) {
                $byKey[(string) $result['key']] = $result;
            }
        }

        $violations = $companySpecific['forbidden_claims']['violations'] ?? [];
        $hasForbiddenViolation = is_array($violations) && $violations !== [];

        $triggered = [];

        foreach ($caps as $cap) {
            if (! is_array($cap)) {
                continue;
            }

            $type = (string) ($cap['trigger_type'] ?? '');
            $matched = false;

            if ($type === CompanyScorecardCap::TRIGGER_CRITERION_CRITICAL_FAILURE) {
                $criterionKey = trim((string) ($cap['criterion_key'] ?? ''));
                if ($criterionKey === '') {
                    foreach ($byKey as $result) {
                        if (($result['critical_failure'] ?? false) === true) {
                            $matched = true;
                            break;
                        }
                    }
                } elseif (($byKey[$criterionKey]['critical_failure'] ?? false) === true) {
                    $matched = true;
                }
            }

            if ($type === CompanyScorecardCap::TRIGGER_FORBIDDEN_CLAIM_VIOLATION && $hasForbiddenViolation) {
                $matched = true;
            }

            if (! $matched) {
                continue;
            }

            $triggered[] = [
                'id' => $cap['id'] ?? null,
                'name' => (string) ($cap['name'] ?? ''),
                'criterion_key' => $cap['criterion_key'] ?? null,
                'trigger_type' => $type,
                'max_total_score' => (int) ($cap['max_total_score'] ?? 100),
                'description' => $cap['description'] ?? null,
            ];
        }

        return $triggered;
    }
}
