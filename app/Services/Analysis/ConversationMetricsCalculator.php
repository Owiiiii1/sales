<?php

namespace App\Services\Analysis;

use App\Models\Transcript;
use App\Services\Analysis\DTO\SalesAnalysisResult;

class ConversationMetricsCalculator
{
    public function attach(Transcript $transcript, SalesAnalysisResult $result): SalesAnalysisResult
    {
        $roles = is_array($result->payload['speaker_roles'] ?? null) ? $result->payload['speaker_roles'] : [];
        $result->payload['conversation_metrics'] = $this->for($transcript, $roles);

        $stageMap = is_array($result->payload['sales_stage_map'] ?? null)
            ? $result->payload['sales_stage_map']
            : [];
        $stageMetrics = $this->byStage($transcript, $roles, $stageMap);
        $result->payload['stage_talk_metrics'] = $stageMetrics;
        $result->payload['discovery_talk_balance'] = $this->discoveryTalkBalance($stageMetrics);

        return $result;
    }

    /**
     * @param  array<int|string, string>  $speakerRoles
     * @return array<string, int|null>
     */
    public function for(?Transcript $transcript, array $speakerRoles): array
    {
        $empty = SalesAnalysisSchema::emptyConversationMetrics();

        if ($transcript === null) {
            return $empty;
        }

        $segments = $this->orderedSegments($transcript);

        if ($segments === []) {
            return $empty;
        }

        $switches = 0;
        $sellerSeconds = 0.0;
        $customerSeconds = 0.0;
        $longestSeller = 0.0;
        $currentSellerRun = 0.0;
        $hasDuration = false;
        $maxEnd = 0.0;
        $previousSpeaker = null;

        foreach ($segments as $segment) {
            $speaker = (int) $segment->speaker;
            $role = $this->role($speakerRoles, $speaker);
            $window = $this->segmentWindow($segment);

            if ($window === null) {
                if ($previousSpeaker !== null && $speaker !== $previousSpeaker) {
                    $switches++;
                }
                $previousSpeaker = $speaker;
                $currentSellerRun = 0.0;

                continue;
            }

            [$start, $end, $duration] = $window;

            if ($end > $maxEnd) {
                $maxEnd = $end;
            }

            if ($previousSpeaker !== null && $speaker !== $previousSpeaker) {
                $switches++;
            }

            $sameSellerTurn = $previousSpeaker !== null && $speaker === $previousSpeaker && $role === 'seller';

            if ($duration > 0) {
                $hasDuration = true;

                if ($role === 'seller') {
                    $sellerSeconds += $duration;
                    $currentSellerRun = $sameSellerTurn ? $currentSellerRun + $duration : $duration;
                    $longestSeller = max($longestSeller, $currentSellerRun);
                } else {
                    $currentSellerRun = 0.0;
                    if ($role === 'customer') {
                        $customerSeconds += $duration;
                    }
                }
            } elseif (! $sameSellerTurn) {
                $currentSellerRun = 0.0;
            }

            $previousSpeaker = $speaker;
        }

        $identified = $sellerSeconds + $customerSeconds;
        $sellerPercent = null;
        $customerPercent = null;

        if ($hasDuration && $identified > 0) {
            $sellerPercent = (int) round(($sellerSeconds / $identified) * 100);
            $customerPercent = 100 - $sellerPercent;
        }

        $callDuration = $transcript->duration_seconds !== null
            ? (int) round((float) $transcript->duration_seconds)
            : ($maxEnd > 0 ? (int) round($maxEnd) : null);

        return [
            'seller_talk_percent' => $sellerPercent,
            'customer_talk_percent' => $customerPercent,
            'longest_seller_monologue_seconds' => $hasDuration && $longestSeller > 0 ? (int) round($longestSeller) : null,
            'speaker_switches' => $switches,
            'call_duration_seconds' => $callDuration,
        ];
    }

    /**
     * @param  array<int|string, string>  $speakerRoles
     * @param  array<int, mixed>  $stageMap
     * @return array<int, array<string, mixed>>
     */
    public function byStage(?Transcript $transcript, array $speakerRoles, array $stageMap): array
    {
        if ($transcript === null || $stageMap === []) {
            return [];
        }

        $segments = $this->orderedSegments($transcript);
        if ($segments === []) {
            return [];
        }

        $metrics = [];

        foreach ($stageMap as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $stage = strtolower(trim((string) ($entry['stage'] ?? '')));
            $bounds = $this->stageBounds($entry);

            if ($stage === '' || $bounds === null) {
                continue;
            }

            [$stageStart, $stageEnd] = $bounds;
            $sellerSeconds = 0.0;
            $customerSeconds = 0.0;
            $switches = 0;
            $previousSpeaker = null;
            $hasOverlap = false;

            foreach ($segments as $segment) {
                $window = $this->segmentWindow($segment);
                if ($window === null) {
                    continue;
                }

                [$start, $end, $duration] = $window;
                if ($duration <= 0 || $end <= $stageStart || $start >= $stageEnd) {
                    continue;
                }

                $overlap = min($end, $stageEnd) - max($start, $stageStart);
                if ($overlap <= 0) {
                    continue;
                }

                $hasOverlap = true;
                $speaker = (int) $segment->speaker;
                $role = $this->role($speakerRoles, $speaker);

                if ($previousSpeaker !== null && $speaker !== $previousSpeaker) {
                    $switches++;
                }
                $previousSpeaker = $speaker;

                if ($role === 'seller') {
                    $sellerSeconds += $overlap;
                } elseif ($role === 'customer') {
                    $customerSeconds += $overlap;
                }
            }

            $identified = $sellerSeconds + $customerSeconds;
            $sellerPercent = null;
            $customerPercent = null;

            if ($hasOverlap && $identified > 0) {
                $sellerPercent = (int) round(($sellerSeconds / $identified) * 100);
                $customerPercent = 100 - $sellerPercent;
            }

            $metrics[] = [
                'stage' => $stage,
                'start_seconds' => $stageStart,
                'end_seconds' => $stageEnd,
                'duration_seconds' => (int) round($stageEnd - $stageStart),
                'seller_talk_percent' => $sellerPercent,
                'customer_talk_percent' => $customerPercent,
                'speaker_switches' => $hasOverlap ? $switches : null,
            ];
        }

        return $metrics;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stageMetrics
     * @return array{seller_talk_percent:int, customer_talk_percent:int}|null
     */
    public function discoveryTalkBalance(array $stageMetrics): ?array
    {
        foreach ($stageMetrics as $row) {
            if (($row['stage'] ?? '') !== 'discovery') {
                continue;
            }

            if (! isset($row['seller_talk_percent'], $row['customer_talk_percent'])) {
                return null;
            }

            return [
                'seller_talk_percent' => (int) $row['seller_talk_percent'],
                'customer_talk_percent' => (int) $row['customer_talk_percent'],
            ];
        }

        return null;
    }

    /**
     * @return array<int, object>
     */
    private function orderedSegments(Transcript $transcript): array
    {
        $segments = $transcript->relationLoaded('segments')
            ? $transcript->segments
            : $transcript->segments()->orderBy('sequence')->get();

        return $segments->sortBy([
            ['sequence', 'asc'],
            ['start_seconds', 'asc'],
        ])->values()->all();
    }

    /**
     * @return array{0:float,1:float,2:float}|null
     */
    private function segmentWindow(object $segment): ?array
    {
        $start = $this->seconds($segment->start_seconds ?? null);
        if ($start === null) {
            return null;
        }

        $end = $this->seconds($segment->end_seconds ?? null);
        if ($end === null) {
            $end = $start;
        }

        if ($end < $start) {
            return null;
        }

        return [$start, $end, $end - $start];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{0:float,1:float}|null
     */
    private function stageBounds(array $entry): ?array
    {
        $start = $this->seconds($entry['start_seconds'] ?? null);
        $end = $this->seconds($entry['end_seconds'] ?? null);

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return [$start, $end];
    }

    private function seconds(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $seconds = (float) $value;

        if (! is_finite($seconds) || $seconds < 0) {
            return null;
        }

        return $seconds;
    }

    /**
     * @param  array<int|string, string>  $speakerRoles
     */
    private function role(array $speakerRoles, int $speaker): string
    {
        if (isset($speakerRoles[(string) $speaker]) && is_string($speakerRoles[(string) $speaker])) {
            return $speakerRoles[(string) $speaker];
        }

        if (isset($speakerRoles[$speaker]) && is_string($speakerRoles[$speaker])) {
            return $speakerRoles[$speaker];
        }

        return 'unknown';
    }
}
