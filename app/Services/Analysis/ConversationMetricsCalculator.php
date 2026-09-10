<?php

namespace App\Services\Analysis;

use App\Models\Transcript;
use App\Services\Analysis\DTO\SalesAnalysisResult;

class ConversationMetricsCalculator
{
    public function attach(Transcript $transcript, SalesAnalysisResult $result): SalesAnalysisResult
    {
        $result->payload['conversation_metrics'] = $this->for(
            $transcript,
            is_array($result->payload['speaker_roles'] ?? null) ? $result->payload['speaker_roles'] : [],
        );

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

        $segments = $transcript->relationLoaded('segments')
            ? $transcript->segments
            : $transcript->segments()->orderBy('sequence')->get();

        if ($segments->isEmpty()) {
            return $empty;
        }

        $ordered = $segments->sortBy([
            ['sequence', 'asc'],
            ['start_seconds', 'asc'],
        ])->values();

        $switches = 0;
        $sellerSeconds = 0.0;
        $customerSeconds = 0.0;
        $longestSeller = 0.0;
        $currentSellerRun = 0.0;
        $hasDuration = false;
        $maxEnd = 0.0;
        $previousSpeaker = null;

        foreach ($ordered as $segment) {
            $speaker = (int) $segment->speaker;
            $role = $this->role($speakerRoles, $speaker);
            $start = (float) $segment->start_seconds;
            $end = $segment->end_seconds !== null ? (float) $segment->end_seconds : $start;
            $duration = max(0.0, $end - $start);

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
