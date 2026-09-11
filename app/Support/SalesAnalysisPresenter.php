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
            'company_context_used' => (bool) ($analysis->result['company_context_used'] ?? false),
            'company_scorecard_score' => $analysis->company_scorecard_score,
            'scorecard_name' => $analysis->scorecard_snapshot['name'] ?? null,
            'context_snapshot' => $analysis->context_snapshot,
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
            'customer_intent_confidence' => $result['customer_intent_confidence'] ?? null,
            'speaker_roles' => $roles,
            'speaker_roles_confidence' => $result['speaker_roles_confidence'] ?? null,
            'sections' => $sections,
            'strengths' => self::findings($result['strengths'] ?? []),
            'weaknesses' => self::findings($result['weaknesses'] ?? []),
            'missed_opportunities' => self::findings($result['missed_opportunities'] ?? []),
            'buying_signals' => self::findings($result['buying_signals'] ?? []),
            'objections_detected' => self::findings($result['objections_detected'] ?? []),
            'recommendations' => self::findings($result['recommendations'] ?? []),
            'better_phrases' => self::phrases($result['better_phrases'] ?? []),
            'next_step' => $result['next_step'] ?? '',
            'company_context_used' => (bool) ($result['company_context_used'] ?? false),
            'company_scorecard_score' => $analysis->company_scorecard_score,
            'company_specific' => $result['company_specific'] ?? null,
            'executive_summary' => is_array($result['executive_summary'] ?? null) ? $result['executive_summary'] : null,
            'call_objective' => is_array($result['call_objective'] ?? null) ? $result['call_objective'] : null,
            'conversation_control' => self::control($result['conversation_control'] ?? null),
            'customer_signals' => self::customerSignals($result['customer_signals'] ?? null),
            'missed_signals' => self::missedSignals($result['missed_signals'] ?? []),
            'discovery_depth' => is_array($result['discovery_depth'] ?? null) ? $result['discovery_depth'] : null,
            'question_analysis' => self::questionAnalysis($result['question_analysis'] ?? null),
            'listening' => self::namedFindings($result['listening'] ?? null, ['good_listening_moments', 'interruptions_or_ignored_points', 'follow_up_quality']),
            'value_communication' => self::namedFindings($result['value_communication'] ?? null, ['generic_pitch_moments', 'strong_value_moments']),
            'objection_map' => self::objectionMap($result['objection_map'] ?? []),
            'negotiation' => is_array($result['negotiation'] ?? null) ? $result['negotiation'] : null,
            'trust_rapport' => self::namedFindings($result['trust_rapport'] ?? null, ['trust_building_moments', 'trust_reducing_moments']),
            'closing' => self::namedFindings($result['closing'] ?? null, ['missed_closing_opportunities']),
            'timeline' => self::timeline($result['timeline'] ?? []),
            'turning_points' => is_array($result['turning_points'] ?? null) ? $result['turning_points'] : [],
            'critical_mistakes' => is_array($result['critical_mistakes'] ?? null) ? $result['critical_mistakes'] : [],
            'what_to_repeat' => self::practices($result['what_to_repeat'] ?? []),
            'what_to_stop' => self::practices($result['what_to_stop'] ?? []),
            'what_to_start' => self::practices($result['what_to_start'] ?? []),
            'coaching_priorities' => is_array($result['coaching_priorities'] ?? null) ? $result['coaching_priorities'] : [],
            'next_call_playbook' => is_array($result['next_call_playbook'] ?? null) ? $result['next_call_playbook'] : null,
            'alternative_path' => is_array($result['alternative_path'] ?? null) ? $result['alternative_path'] : null,
            'outcome_analysis' => is_array($result['outcome_analysis'] ?? null) ? $result['outcome_analysis'] : null,
            'sales_stage_map' => is_array($result['sales_stage_map'] ?? null) ? $result['sales_stage_map'] : [],
            'conversation_metrics' => is_array($result['conversation_metrics'] ?? null)
                ? $result['conversation_metrics']
                : SalesAnalysisSchema::emptyConversationMetrics(),
            'stage_talk_metrics' => is_array($result['stage_talk_metrics'] ?? null) ? $result['stage_talk_metrics'] : [],
            'discovery_talk_balance' => is_array($result['discovery_talk_balance'] ?? null)
                ? $result['discovery_talk_balance']
                : null,
        ];

        $companySpecific = is_array($result['company_specific'] ?? null) ? $result['company_specific'] : [];
        $scorecard = is_array($companySpecific['scorecard'] ?? null) ? $companySpecific['scorecard'] : [];
        $hasCustomScorecard = $payload['company_context_used']
            && ($analysis->company_scorecard_score !== null || ! empty($analysis->scorecard_snapshot['criteria'] ?? null));

        $payload['primary_score_kind'] = $hasCustomScorecard ? 'company' : 'generic';
        $payload['weighted_company_score'] = isset($scorecard['weighted_score']) && $scorecard['weighted_score'] !== null
            ? (int) $scorecard['weighted_score']
            : null;
        $payload['triggered_caps'] = is_array($scorecard['triggered_caps'] ?? null) ? $scorecard['triggered_caps'] : [];
        $payload['company_score_band'] = CompanyScoreBands::label(
            $analysis->company_scorecard_score,
            is_array($analysis->scorecard_snapshot['score_bands'] ?? null)
                ? $analysis->scorecard_snapshot['score_bands']
                : null,
        );

        unset($admin);

        return $payload;
    }

    /**
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
                    'timestamp_seconds' => null,
                    'timestamp_label' => null,
                    'original' => '',
                    'problem' => '',
                    'better' => $item,
                    'suggested' => $item,
                    'why_better' => '',
                    'reason' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'timestamp_seconds' => isset($item['timestamp_seconds']) && $item['timestamp_seconds'] !== null
                    ? (float) $item['timestamp_seconds']
                    : null,
                'timestamp_label' => isset($item['timestamp_seconds']) && $item['timestamp_seconds'] !== null
                    ? TranscriptPresenter::timestamp((float) $item['timestamp_seconds'])
                    : null,
                'original' => (string) ($item['original'] ?? ''),
                'problem' => (string) ($item['problem'] ?? ''),
                'better' => (string) ($item['better'] ?? $item['suggested'] ?? ''),
                'suggested' => (string) ($item['better'] ?? $item['suggested'] ?? ''),
                'why_better' => (string) ($item['why_better'] ?? $item['reason'] ?? ''),
                'reason' => (string) ($item['why_better'] ?? $item['reason'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function control(mixed $control): ?array
    {
        if (! is_array($control)) {
            return null;
        }

        $control['loss_of_control_moments'] = self::moments($control['loss_of_control_moments'] ?? []);
        $control['recovery_moments'] = self::moments($control['recovery_moments'] ?? []);

        return $control;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function moments(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'timestamp_seconds' => null,
                    'timestamp_label' => null,
                    'speaker' => null,
                    'speaker_label' => null,
                    'explanation' => $item,
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
                'timestamp_seconds' => $timestamp,
                'timestamp_label' => $timestamp !== null ? TranscriptPresenter::timestamp($timestamp) : null,
                'speaker' => $speaker,
                'speaker_label' => $speaker !== null ? 'Speaker '.($speaker + 1) : null,
                'explanation' => (string) ($item['explanation'] ?? $item['text'] ?? ''),
                'quote' => isset($item['quote']) ? (string) $item['quote'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    private static function customerSignals(mixed $signals): ?array
    {
        if (! is_array($signals)) {
            return null;
        }

        $keys = ['positive_signals', 'negative_signals', 'buying_signals', 'hesitation_signals', 'trust_signals', 'risk_signals'];
        $normalized = [];
        foreach ($keys as $key) {
            $normalized[$key] = self::signalList($signals[$key] ?? []);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function signalList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'timestamp_seconds' => null,
                    'timestamp_label' => null,
                    'speaker' => null,
                    'speaker_label' => null,
                    'signal' => $item,
                    'why_it_matters' => '',
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
                'timestamp_seconds' => $timestamp,
                'timestamp_label' => $timestamp !== null ? TranscriptPresenter::timestamp($timestamp) : null,
                'speaker' => $speaker,
                'speaker_label' => $speaker !== null ? 'Speaker '.($speaker + 1) : null,
                'signal' => (string) ($item['signal'] ?? $item['text'] ?? ''),
                'why_it_matters' => (string) ($item['why_it_matters'] ?? ''),
                'quote' => isset($item['quote']) ? (string) $item['quote'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function missedSignals(mixed $items): array
    {
        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function questionAnalysis(mixed $analysis): ?array
    {
        if (! is_array($analysis)) {
            return null;
        }

        foreach (['open_questions', 'closed_questions', 'strong_questions', 'weak_questions', 'missed_questions'] as $key) {
            $analysis[$key] = is_array($analysis[$key] ?? null) ? $analysis[$key] : [];
        }

        return $analysis;
    }

    /**
     * @param  array<int, string>  $findingKeys
     * @return array<string, mixed>|null
     */
    private static function namedFindings(mixed $block, array $findingKeys): ?array
    {
        if (! is_array($block)) {
            return null;
        }

        foreach ($findingKeys as $key) {
            $block[$key] = self::findings($block[$key] ?? []);
        }

        return $block;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function objectionMap(mixed $items): array
    {
        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function timeline(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $speaker = isset($item['speaker']) && $item['speaker'] !== null ? (int) $item['speaker'] : null;
            $timestamp = isset($item['timestamp_seconds']) && $item['timestamp_seconds'] !== null
                ? (float) $item['timestamp_seconds']
                : null;

            $normalized[] = [
                'timestamp_seconds' => $timestamp,
                'timestamp_label' => $timestamp !== null ? TranscriptPresenter::timestamp($timestamp) : null,
                'type' => (string) ($item['type'] ?? 'positive'),
                'title' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'speaker' => $speaker,
                'speaker_label' => $speaker !== null ? 'Speaker '.($speaker + 1) : null,
                'quote' => isset($item['quote']) ? (string) $item['quote'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{text:string, why:string}>
     */
    private static function practices(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = ['text' => $item, 'why' => ''];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'text' => (string) ($item['text'] ?? $item['practice'] ?? ''),
                'why' => (string) ($item['why'] ?? ''),
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
