<?php

namespace App\Support;

use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Services\Analysis\SalesAnalysisSchema;

class SalesAnalysisPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function public(Call $call): ?array
    {
        $analysis = $call->analysis;

        if ($analysis === null || $call->status !== 'completed') {
            return null;
        }

        return self::payload($analysis, admin: false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function admin(Call $call): ?array
    {
        $analysis = $call->analysis;

        if ($analysis === null) {
            return null;
        }

        return array_merge(self::payload($analysis, admin: true), [
            'provider' => $analysis->provider,
            'provider_label' => self::providerLabel((string) $analysis->provider),
            'model' => $analysis->model,
            'schema_version' => $analysis->schema_version,
            'started_at' => optional($analysis->started_at)?->toIso8601String(),
            'completed_at' => optional($analysis->completed_at)?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(SalesAnalysis $analysis, bool $admin): array
    {
        $result = is_array($analysis->result) ? $analysis->result : [];
        $sections = [];

        foreach (SalesAnalysisSchema::SECTION_KEYS as $key) {
            $section = is_array($result['sections'][$key] ?? null) ? $result['sections'][$key] : [];
            $sections[$key] = [
                'key' => $key,
                'title' => SalesAnalysisSchema::SECTION_TITLES[$key],
                'applicable' => (bool) ($section['applicable'] ?? true),
                'score' => $section['score'] ?? null,
                'summary' => $section['summary'] ?? '',
                'strengths' => self::findings($section['strengths'] ?? []),
                'issues' => self::findings($section['issues'] ?? []),
            ];
        }

        $roles = [];
        foreach (is_array($result['speaker_roles'] ?? null) ? $result['speaker_roles'] : [] as $speaker => $role) {
            $roles[] = [
                'speaker' => (int) $speaker,
                'speaker_label' => 'Speaker '.(((int) $speaker) + 1),
                'role' => $role,
            ];
        }

        $payload = [
            'overall_score' => $analysis->overall_score ?? ($result['overall_score'] ?? null),
            'summary' => $analysis->summary ?? ($result['summary'] ?? ''),
            'call_outcome' => $result['call_outcome'] ?? null,
            'customer_intent' => $result['customer_intent'] ?? null,
            'speaker_roles' => $roles,
            'sections' => $sections,
            'strengths' => self::findings($result['strengths'] ?? []),
            'weaknesses' => self::findings($result['weaknesses'] ?? []),
            'missed_opportunities' => self::findings($result['missed_opportunities'] ?? []),
            'buying_signals' => self::findings($result['buying_signals'] ?? []),
            'objections_detected' => self::findings($result['objections_detected'] ?? []),
            'recommendations' => self::findings($result['recommendations'] ?? []),
            'better_phrases' => self::phrases($result['better_phrases'] ?? []),
            'next_step' => $result['next_step'] ?? '',
        ];

        unset($admin);

        return $payload;
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{text:string, speaker:?int, speaker_label:?string, timestamp_seconds:?float, timestamp_label:?string, quote:?string}>
     */
    private static function findings(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'text' => $item,
                    'speaker' => null,
                    'speaker_label' => null,
                    'timestamp_seconds' => null,
                    'timestamp_label' => null,
                    'quote' => null,
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $speaker = isset($item['speaker']) && $item['speaker'] !== null ? (int) $item['speaker'] : null;
            $timestamp = isset($item['timestamp_seconds']) && $item['timestamp_seconds'] !== null
                ? (float) $item['timestamp_seconds']
                : null;

            $normalized[] = [
                'text' => (string) ($item['text'] ?? ''),
                'speaker' => $speaker,
                'speaker_label' => $speaker !== null ? 'Speaker '.($speaker + 1) : null,
                'timestamp_seconds' => $timestamp,
                'timestamp_label' => $timestamp !== null ? TranscriptPresenter::timestamp($timestamp) : null,
                'quote' => isset($item['quote']) ? (string) $item['quote'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{original:string, suggested:string, reason:string}>
     */
    private static function phrases(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'original' => '',
                    'suggested' => $item,
                    'reason' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'original' => (string) ($item['original'] ?? ''),
                'suggested' => (string) ($item['suggested'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }

        return $normalized;
    }

    public static function providerLabel(?string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'anthropic' => 'Claude',
            'gemini' => 'Gemini',
            default => $provider ?: '—',
        };
    }
}
