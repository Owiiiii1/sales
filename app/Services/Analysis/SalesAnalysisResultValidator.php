<?php

namespace App\Services\Analysis;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\DTO\SalesAnalysisResult;

class SalesAnalysisResultValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload, string $provider, string $model, ?AnalysisContext $context = null): SalesAnalysisResult
    {
        $context ??= new AnalysisContext;
        $required = [
            'overall_score',
            'summary',
            'call_outcome',
            'customer_intent',
            'speaker_roles',
            'sections',
            'strengths',
            'weaknesses',
            'missed_opportunities',
            'buying_signals',
            'objections_detected',
            'recommendations',
            'better_phrases',
            'next_step',
            ...SalesAnalysisSchema::V3_REQUIRED,
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new PermanentAnalysisException("Analysis JSON is missing required key: {$key}");
            }
        }

        $overall = $this->score($payload['overall_score'], 'overall_score');
        $summary = $this->string($payload['summary'], 'summary');
        $outcome = $this->enum($payload['call_outcome'], SalesAnalysisSchema::OUTCOMES, 'call_outcome');
        $intent = $this->enum($payload['customer_intent'], SalesAnalysisSchema::INTENTS, 'customer_intent');

        if (! is_array($payload['speaker_roles'])) {
            throw new PermanentAnalysisException('speaker_roles must be an object.');
        }

        $roles = [];
        foreach ($payload['speaker_roles'] as $speaker => $role) {
            if (! is_numeric($speaker) && ! is_string($speaker)) {
                throw new PermanentAnalysisException('speaker_roles keys must be speaker identifiers.');
            }
            $roles[(string) $speaker] = $this->enum($role, SalesAnalysisSchema::SPEAKER_ROLES, 'speaker_roles.'.$speaker);
        }

        if (! is_array($payload['sections'])) {
            throw new PermanentAnalysisException('sections must be an object.');
        }

        $sections = [];
        foreach (SalesAnalysisSchema::SECTION_KEYS as $key) {
            if (! array_key_exists($key, $payload['sections']) || ! is_array($payload['sections'][$key])) {
                throw new PermanentAnalysisException("sections.{$key} is required.");
            }
            $sections[$key] = $this->section($payload['sections'][$key], $key);
        }

        $normalized = [
            'overall_score' => $overall,
            'summary' => $summary,
            'call_outcome' => $outcome,
            'customer_intent' => $intent,
            'customer_intent_confidence' => $this->enum(
                $payload['customer_intent_confidence'],
                SalesAnalysisSchema::CONFIDENCE,
                'customer_intent_confidence',
            ),
            'speaker_roles' => $roles,
            'speaker_roles_confidence' => array_key_exists('speaker_roles_confidence', $payload) && $payload['speaker_roles_confidence'] !== null
                ? $this->enum($payload['speaker_roles_confidence'], SalesAnalysisSchema::CONFIDENCE, 'speaker_roles_confidence')
                : null,
            'sections' => $sections,
            'strengths' => $this->findings($payload['strengths'], 'strengths'),
            'weaknesses' => $this->findings($payload['weaknesses'], 'weaknesses'),
            'missed_opportunities' => $this->findings($payload['missed_opportunities'], 'missed_opportunities'),
            'buying_signals' => $this->findings($payload['buying_signals'], 'buying_signals'),
            'objections_detected' => $this->findings($payload['objections_detected'], 'objections_detected'),
            'recommendations' => $this->findings($payload['recommendations'], 'recommendations'),
            'better_phrases' => $this->phrases($payload['better_phrases'], 'better_phrases'),
            'next_step' => $this->string($payload['next_step'], 'next_step'),
        ];

        $normalized = array_merge($normalized, $this->deep($payload));

        $companySpecific = $this->companySpecific($payload, $context);
        $normalized['company_context_used'] = $context->companyContextUsed;
        $normalized['company_specific'] = $companySpecific;

        return new SalesAnalysisResult(
            provider: $provider,
            model: $model,
            schemaVersion: SalesAnalysisSchema::VERSION,
            overallScore: $overall,
            summary: $summary,
            payload: $normalized,
            companyContextUsed: $context->companyContextUsed,
            companyScorecardScore: $companySpecific['scorecard']['total_score'] ?? null,
            companyContextHash: $context->companyContextHash,
            scorecardId: $context->scorecardId,
            scorecardSnapshot: $context->scorecardSnapshot,
            contextSnapshot: $context->snapshot,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function companySpecific(array $payload, AnalysisContext $context): array
    {
        $raw = $payload['company_specific'] ?? null;

        if ($raw === null) {
            if ($context->companyContextUsed) {
                throw new PermanentAnalysisException('company_specific is required when company context is used.');
            }

            return SalesAnalysisSchema::emptyCompanySpecific();
        }

        if (! is_array($raw)) {
            throw new PermanentAnalysisException('company_specific must be an object.');
        }

        if (array_key_exists('company_context_used', $payload) && $payload['company_context_used'] !== $context->companyContextUsed) {
            throw new PermanentAnalysisException('company_context_used does not match the analysis context.');
        }

        $script = is_array($raw['script_adherence'] ?? null) ? $raw['script_adherence'] : ['applicable' => false, 'summary' => '', 'issues' => []];
        $questions = is_array($raw['mandatory_questions'] ?? null) ? $raw['mandatory_questions'] : [];
        $claims = is_array($raw['forbidden_claims'] ?? null) ? $raw['forbidden_claims'] : [];
        $objections = is_array($raw['objection_handling'] ?? null) ? $raw['objection_handling'] : [];
        $offerings = is_array($raw['offering_accuracy'] ?? null) ? $raw['offering_accuracy'] : [];

        $criteria = $this->scorecardCriteria($raw['scorecard']['criteria'] ?? [], $context);
        $forbiddenViolations = $this->findings($claims['violations'] ?? [], 'company_specific.forbidden_claims.violations');
        $scored = app(CompanyScoreCalculator::class)->scored(
            $criteria,
            $context->scorecardSnapshot ?? ['criteria' => [], 'caps' => []],
            ['forbidden_claims' => ['violations' => $forbiddenViolations]],
        );

        return [
            'script_adherence' => [
                'applicable' => $this->boolean($script['applicable'] ?? false, 'company_specific.script_adherence.applicable'),
                'score' => isset($script['score']) && $script['score'] !== null
                    ? $this->score($script['score'], 'company_specific.script_adherence.score')
                    : null,
                'summary' => $this->string($script['summary'] ?? '', 'company_specific.script_adherence.summary'),
                'issues' => $this->findings($script['issues'] ?? [], 'company_specific.script_adherence.issues'),
            ],
            'mandatory_questions' => [
                'asked' => $this->stringList($questions['asked'] ?? [], 'company_specific.mandatory_questions.asked'),
                'missed' => $this->stringList($questions['missed'] ?? [], 'company_specific.mandatory_questions.missed'),
            ],
            'forbidden_claims' => [
                'violations' => $forbiddenViolations,
            ],
            'objection_handling' => [
                'matched' => $this->matchedObjections($objections['matched'] ?? []),
            ],
            'offering_accuracy' => [
                'issues' => $this->findings($offerings['issues'] ?? [], 'company_specific.offering_accuracy.issues'),
            ],
            'scorecard' => [
                'weighted_score' => $scored['weighted_score'],
                'total_score' => $scored['total_score'],
                'triggered_caps' => $scored['triggered_caps'],
                'criteria' => $criteria,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scorecardCriteria(mixed $items, AnalysisContext $context): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('company_specific.scorecard.criteria must be an array.');
        }

        $expected = [];
        foreach ($context->scorecardSnapshot['criteria'] ?? [] as $criterion) {
            $expected[(string) $criterion['key']] = $criterion;
        }

        $seen = [];
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item) || ! filled($item['key'] ?? null)) {
                continue;
            }

            $key = $this->string($item['key'], "company_specific.scorecard.criteria.{$index}.key");

            if (isset($seen[$key])) {
                throw new PermanentAnalysisException("Duplicate scorecard criterion: {$key}.");
            }

            if ($expected !== [] && ! isset($expected[$key])) {
                throw new PermanentAnalysisException("Unknown scorecard criterion: {$key}.");
            }

            $seen[$key] = true;
            $max = (int) ($expected[$key]['max_score'] ?? $item['max_score'] ?? 100);
            $applicable = $this->boolean($item['applicable'] ?? true, "company_specific.scorecard.criteria.{$index}.applicable");

            $normalized[] = [
                'key' => $key,
                'name' => (string) ($expected[$key]['name'] ?? $item['name'] ?? $key),
                'score' => $applicable ? $this->boundedScore($item['score'] ?? null, $max, "company_specific.scorecard.criteria.{$index}.score") : null,
                'max_score' => $max,
                'applicable' => $applicable,
                'summary' => $this->string($item['summary'] ?? '', "company_specific.scorecard.criteria.{$index}.summary"),
                'evidence' => $this->findings($item['evidence'] ?? [], "company_specific.scorecard.criteria.{$index}.evidence"),
                'critical_failure' => $this->boolean($item['critical_failure'] ?? false, "company_specific.scorecard.criteria.{$index}.critical_failure"),
            ];
        }

        if ($expected !== [] && count($seen) * 2 < count($expected)) {
            throw new TransientAnalysisException('Company scorecard output is incomplete: majority of expected criteria are missing.');
        }

        foreach ($expected as $key => $criterion) {
            if (! isset($seen[$key])) {
                $normalized[] = [
                    'key' => $key,
                    'name' => (string) ($criterion['name'] ?? $key),
                    'score' => null,
                    'max_score' => (int) ($criterion['max_score'] ?? 100),
                    'applicable' => false,
                    'summary' => '',
                    'evidence' => [],
                    'critical_failure' => false,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $values = [];
        foreach (array_values($items) as $index => $item) {
            if (is_array($item) && array_key_exists('text', $item)) {
                $item = $item['text'];
            }

            $value = $this->string($item, $path.'.'.$index);
            if ($this->blank($value)) {
                continue;
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @return array<int, array{objection:string, handled:bool, summary:string}>
     */
    private function matchedObjections(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('company_specific.objection_handling.matched must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'objection' => $item,
                    'handled' => true,
                    'summary' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("company_specific.objection_handling.matched.{$index} must be an object.");
            }

            $normalized[] = [
                'objection' => $this->string($item['objection'] ?? $item['text'] ?? '', "company_specific.objection_handling.matched.{$index}.objection"),
                'handled' => $this->boolean($item['handled'] ?? true, "company_specific.objection_handling.matched.{$index}.handled"),
                'summary' => $this->string($item['summary'] ?? '', "company_specific.objection_handling.matched.{$index}.summary"),
            ];
        }

        return $normalized;
    }

    private function boundedScore(mixed $value, int $max, string $path): int
    {
        $max = max(1, $max);

        if (is_int($value) && $value >= 0 && $value <= $max) {
            return $value;
        }

        if (is_float($value) && $value >= 0 && $value <= $max && floor($value) === $value) {
            return (int) $value;
        }

        throw new PermanentAnalysisException("{$path} must be an integer between 0 and {$max}.");
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function section(array $section, string $key): array
    {
        $applicable = array_key_exists('applicable', $section)
            ? $this->boolean($section['applicable'], "sections.{$key}.applicable")
            : true;

        $score = null;
        if ($applicable) {
            if (! array_key_exists('score', $section)) {
                throw new PermanentAnalysisException("sections.{$key}.score is required when the section is applicable.");
            }
            $score = $this->score($section['score'], "sections.{$key}.score");
        } elseif (array_key_exists('score', $section) && $section['score'] !== null) {
            $score = $this->score($section['score'], "sections.{$key}.score");
        }

        return [
            'applicable' => $applicable,
            'score' => $score,
            'summary' => $this->string($section['summary'] ?? '', "sections.{$key}.summary"),
            'strengths' => $this->findings($section['strengths'] ?? [], "sections.{$key}.strengths"),
            'issues' => $this->findings($section['issues'] ?? [], "sections.{$key}.issues"),
        ];
    }

    /**
     * @return array<int, array{text:string, speaker:?int, timestamp_seconds:?float, quote:?string}>
     */
    private function findings(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            $finding = $this->finding($item, $path.'.'.$index);
            if ($this->blank($finding['text'])) {
                continue;
            }
            $normalized[] = $finding;
        }

        return $this->capped($normalized, $path, 'findings');
    }

    /**
     * @return array{text:string, speaker:?int, timestamp_seconds:?float, quote:?string}
     */
    private function finding(mixed $item, string $path): array
    {
        if (is_string($item)) {
            return [
                'text' => $this->string($item, $path),
                'speaker' => null,
                'timestamp_seconds' => null,
                'quote' => null,
            ];
        }

        if (! is_array($item) || ! array_key_exists('text', $item)) {
            throw new PermanentAnalysisException("{$path} must be an object with text.");
        }

        $speaker = $item['speaker'] ?? null;
        if ($speaker !== null && ! is_numeric($speaker)) {
            throw new PermanentAnalysisException("{$path}.speaker must be an integer or null.");
        }

        return [
            'text' => $this->string($item['text'], $path.'.text'),
            'speaker' => $speaker === null ? null : (int) $speaker,
            'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, $path.'.timestamp_seconds'),
            'quote' => isset($item['quote']) && $item['quote'] !== null ? $this->string($item['quote'], $path.'.quote') : null,
        ];
    }

    /**
     * @return array<int, array{original:string, suggested:string, reason:string}>
     */
    private function phrases(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $better = $this->string($item, $path.'.'.$index);
                if ($this->blank($better)) {
                    continue;
                }

                $normalized[] = [
                    'timestamp_seconds' => null,
                    'original' => '',
                    'problem' => '',
                    'better' => $better,
                    'suggested' => $better,
                    'why_better' => '',
                    'reason' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $better = $this->string($item['better'] ?? $item['suggested'] ?? '', $path.'.'.$index.'.better');
            $original = $this->string($item['original'] ?? '', $path.'.'.$index.'.original');
            $why = $this->string($item['why_better'] ?? $item['reason'] ?? '', $path.'.'.$index.'.why_better');

            if ($this->blank($better) && $this->blank($original)) {
                continue;
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, $path.'.'.$index.'.timestamp_seconds'),
                'original' => $original,
                'problem' => $this->string($item['problem'] ?? '', $path.'.'.$index.'.problem'),
                'better' => $better,
                'suggested' => $better,
                'why_better' => $why,
                'reason' => $why,
            ];
        }

        return $this->capped($normalized, $path, 'better_phrases');
    }

    private function score(mixed $value, string $path): int
    {
        if (is_int($value) && $value >= 0 && $value <= 100) {
            return $value;
        }

        if (is_float($value) && $value >= 0 && $value <= 100 && floor($value) === $value) {
            return (int) $value;
        }

        throw new PermanentAnalysisException("{$path} must be an integer between 0 and 100.");
    }

    private function string(mixed $value, string $path): string
    {
        if (! is_string($value)) {
            throw new PermanentAnalysisException("{$path} must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function enum(mixed $value, array $allowed, string $path): string
    {
        $string = $this->string($value, $path);

        if (! in_array($string, $allowed, true)) {
            throw new PermanentAnalysisException("{$path} has an invalid value.");
        }

        return $string;
    }

    private function boolean(mixed $value, string $path): bool
    {
        if (! is_bool($value)) {
            throw new PermanentAnalysisException("{$path} must be a boolean.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function deep(array $payload): array
    {
        $executive = $this->object($payload['executive_summary'], 'executive_summary');
        $objective = $this->object($payload['call_objective'], 'call_objective');
        $control = $this->object($payload['conversation_control'], 'conversation_control');
        $signals = $this->object($payload['customer_signals'], 'customer_signals');
        $discovery = $this->object($payload['discovery_depth'], 'discovery_depth');
        $questions = $this->object($payload['question_analysis'], 'question_analysis');
        $listening = $this->object($payload['listening'], 'listening');
        $value = $this->object($payload['value_communication'], 'value_communication');
        $negotiation = $this->object($payload['negotiation'], 'negotiation');
        $trust = $this->object($payload['trust_rapport'], 'trust_rapport');
        $closing = $this->object($payload['closing'], 'closing');
        $playbook = $this->object($payload['next_call_playbook'], 'next_call_playbook');
        $path = $this->object($payload['alternative_path'], 'alternative_path');
        $outcome = $this->object($payload['outcome_analysis'], 'outcome_analysis');

        $negotiationApplicable = $this->boolean($negotiation['applicable'] ?? false, 'negotiation.applicable');

        return [
            'executive_summary' => [
                'one_sentence' => $this->string($executive['one_sentence'] ?? '', 'executive_summary.one_sentence'),
                'what_happened' => $this->string($executive['what_happened'] ?? '', 'executive_summary.what_happened'),
                'why_it_ended_this_way' => $this->string($executive['why_it_ended_this_way'] ?? '', 'executive_summary.why_it_ended_this_way'),
                'biggest_strength' => $this->string($executive['biggest_strength'] ?? '', 'executive_summary.biggest_strength'),
                'biggest_problem' => $this->string($executive['biggest_problem'] ?? '', 'executive_summary.biggest_problem'),
                'best_next_action' => $this->string($executive['best_next_action'] ?? '', 'executive_summary.best_next_action'),
            ],
            'call_objective' => [
                'seller_objective' => $this->string($objective['seller_objective'] ?? '', 'call_objective.seller_objective'),
                'customer_objective' => $this->string($objective['customer_objective'] ?? '', 'call_objective.customer_objective'),
                'objective_alignment' => $this->enum($objective['objective_alignment'] ?? 'unknown', SalesAnalysisSchema::OBJECTIVE_ALIGNMENT, 'call_objective.objective_alignment'),
                'summary' => $this->string($objective['summary'] ?? '', 'call_objective.summary'),
            ],
            'conversation_control' => [
                'score' => $this->nullableScore($control['score'] ?? null, 'conversation_control.score'),
                'who_led' => $this->enum($control['who_led'] ?? 'unclear', SalesAnalysisSchema::WHO_LED, 'conversation_control.who_led'),
                'summary' => $this->string($control['summary'] ?? '', 'conversation_control.summary'),
                'loss_of_control_moments' => $this->moments($control['loss_of_control_moments'] ?? [], 'conversation_control.loss_of_control_moments'),
                'recovery_moments' => $this->moments($control['recovery_moments'] ?? [], 'conversation_control.recovery_moments'),
            ],
            'customer_signals' => [
                'positive_signals' => $this->signals($signals['positive_signals'] ?? [], 'customer_signals.positive_signals'),
                'negative_signals' => $this->signals($signals['negative_signals'] ?? [], 'customer_signals.negative_signals'),
                'buying_signals' => $this->signals($signals['buying_signals'] ?? [], 'customer_signals.buying_signals'),
                'hesitation_signals' => $this->signals($signals['hesitation_signals'] ?? [], 'customer_signals.hesitation_signals'),
                'trust_signals' => $this->signals($signals['trust_signals'] ?? [], 'customer_signals.trust_signals'),
                'risk_signals' => $this->signals($signals['risk_signals'] ?? [], 'customer_signals.risk_signals'),
            ],
            'missed_signals' => $this->missedSignals($payload['missed_signals']),
            'discovery_depth' => [
                'score' => $this->nullableScore($discovery['score'] ?? null, 'discovery_depth.score'),
                'needs_discovered' => $this->stringList($discovery['needs_discovered'] ?? [], 'discovery_depth.needs_discovered'),
                'needs_not_explored' => $this->stringList($discovery['needs_not_explored'] ?? [], 'discovery_depth.needs_not_explored'),
                'pain_points' => $this->stringList($discovery['pain_points'] ?? [], 'discovery_depth.pain_points'),
                'business_impact_discussed' => $this->stringList($discovery['business_impact_discussed'] ?? [], 'discovery_depth.business_impact_discussed'),
                'decision_criteria_found' => $this->stringList($discovery['decision_criteria_found'] ?? [], 'discovery_depth.decision_criteria_found'),
                'budget_discussed' => $this->boolean($discovery['budget_discussed'] ?? false, 'discovery_depth.budget_discussed'),
                'timeline_discussed' => $this->boolean($discovery['timeline_discussed'] ?? false, 'discovery_depth.timeline_discussed'),
                'decision_process_discussed' => $this->boolean($discovery['decision_process_discussed'] ?? false, 'discovery_depth.decision_process_discussed'),
                'summary' => $this->string($discovery['summary'] ?? '', 'discovery_depth.summary'),
            ],
            'question_analysis' => [
                'total_questions_estimate' => $this->nullableNonNegativeInt($questions['total_questions_estimate'] ?? null, 'question_analysis.total_questions_estimate'),
                'open_questions' => $this->questions($questions['open_questions'] ?? [], 'question_analysis.open_questions'),
                'closed_questions' => $this->questions($questions['closed_questions'] ?? [], 'question_analysis.closed_questions'),
                'strong_questions' => $this->questions($questions['strong_questions'] ?? [], 'question_analysis.strong_questions'),
                'weak_questions' => $this->questions($questions['weak_questions'] ?? [], 'question_analysis.weak_questions'),
                'missed_questions' => $this->questions($questions['missed_questions'] ?? [], 'question_analysis.missed_questions'),
                'question_sequence_quality' => $this->string($questions['question_sequence_quality'] ?? '', 'question_analysis.question_sequence_quality'),
                'summary' => $this->string($questions['summary'] ?? '', 'question_analysis.summary'),
            ],
            'listening' => [
                'score' => $this->nullableScore($listening['score'] ?? null, 'listening.score'),
                'summary' => $this->string($listening['summary'] ?? '', 'listening.summary'),
                'good_listening_moments' => $this->findings($listening['good_listening_moments'] ?? [], 'listening.good_listening_moments'),
                'interruptions_or_ignored_points' => $this->findings($listening['interruptions_or_ignored_points'] ?? [], 'listening.interruptions_or_ignored_points'),
                'follow_up_quality' => $this->findings($listening['follow_up_quality'] ?? [], 'listening.follow_up_quality'),
                'paraphrasing_quality' => $this->string($listening['paraphrasing_quality'] ?? '', 'listening.paraphrasing_quality'),
            ],
            'value_communication' => [
                'score' => $this->nullableScore($value['score'] ?? null, 'value_communication.score'),
                'features_mentioned' => $this->stringList($value['features_mentioned'] ?? [], 'value_communication.features_mentioned'),
                'benefits_mentioned' => $this->stringList($value['benefits_mentioned'] ?? [], 'value_communication.benefits_mentioned'),
                'value_links_to_customer_needs' => $this->stringList($value['value_links_to_customer_needs'] ?? [], 'value_communication.value_links_to_customer_needs'),
                'generic_pitch_moments' => $this->findings($value['generic_pitch_moments'] ?? [], 'value_communication.generic_pitch_moments'),
                'strong_value_moments' => $this->findings($value['strong_value_moments'] ?? [], 'value_communication.strong_value_moments'),
                'summary' => $this->string($value['summary'] ?? '', 'value_communication.summary'),
            ],
            'objection_map' => $this->objectionMap($payload['objection_map']),
            'negotiation' => [
                'applicable' => $negotiationApplicable,
                'score' => $negotiationApplicable
                    ? $this->nullableScore($negotiation['score'] ?? null, 'negotiation.score')
                    : null,
                'price_discussed' => $this->boolean($negotiation['price_discussed'] ?? false, 'negotiation.price_discussed'),
                'discount_discussed' => $this->boolean($negotiation['discount_discussed'] ?? false, 'negotiation.discount_discussed'),
                'seller_defended_value' => $this->string($negotiation['seller_defended_value'] ?? '', 'negotiation.seller_defended_value'),
                'concessions' => $this->stringList($negotiation['concessions'] ?? [], 'negotiation.concessions'),
                'risks' => $this->stringList($negotiation['risks'] ?? [], 'negotiation.risks'),
                'summary' => $this->string($negotiation['summary'] ?? '', 'negotiation.summary'),
            ],
            'trust_rapport' => [
                'score' => $this->nullableScore($trust['score'] ?? null, 'trust_rapport.score'),
                'summary' => $this->string($trust['summary'] ?? '', 'trust_rapport.summary'),
                'trust_building_moments' => $this->findings($trust['trust_building_moments'] ?? [], 'trust_rapport.trust_building_moments'),
                'trust_reducing_moments' => $this->findings($trust['trust_reducing_moments'] ?? [], 'trust_rapport.trust_reducing_moments'),
                'tone_assessment' => $this->string($trust['tone_assessment'] ?? '', 'trust_rapport.tone_assessment'),
            ],
            'closing' => [
                'score' => $this->nullableScore($closing['score'] ?? null, 'closing.score'),
                'next_step_defined' => $this->boolean($closing['next_step_defined'] ?? false, 'closing.next_step_defined'),
                'next_step_specificity' => $this->enum($closing['next_step_specificity'] ?? 'none', SalesAnalysisSchema::NEXT_STEP_SPECIFICITY, 'closing.next_step_specificity'),
                'commitment_level' => $this->enum($closing['commitment_level'] ?? 'none', SalesAnalysisSchema::COMMITMENT, 'closing.commitment_level'),
                'summary' => $this->string($closing['summary'] ?? '', 'closing.summary'),
                'missed_closing_opportunities' => $this->findings($closing['missed_closing_opportunities'] ?? [], 'closing.missed_closing_opportunities'),
                'better_closing' => $this->string($closing['better_closing'] ?? '', 'closing.better_closing'),
            ],
            'timeline' => $this->timeline($payload['timeline']),
            'turning_points' => $this->turningPoints($payload['turning_points']),
            'critical_mistakes' => $this->criticalMistakes($payload['critical_mistakes']),
            'what_to_repeat' => $this->practices($payload['what_to_repeat'], 'what_to_repeat'),
            'what_to_stop' => $this->practices($payload['what_to_stop'], 'what_to_stop'),
            'what_to_start' => $this->practices($payload['what_to_start'], 'what_to_start'),
            'coaching_priorities' => $this->coachingPriorities($payload['coaching_priorities']),
            'next_call_playbook' => [
                'before_call' => $this->capped($this->stringList($playbook['before_call'] ?? [], 'next_call_playbook.before_call'), 'next_call_playbook.before_call', 'playbook_items'),
                'during_call' => $this->capped($this->stringList($playbook['during_call'] ?? [], 'next_call_playbook.during_call'), 'next_call_playbook.during_call', 'playbook_items'),
                'closing' => $this->capped($this->stringList($playbook['closing'] ?? [], 'next_call_playbook.closing'), 'next_call_playbook.closing', 'playbook_items'),
                'follow_up' => $this->capped($this->stringList($playbook['follow_up'] ?? [], 'next_call_playbook.follow_up'), 'next_call_playbook.follow_up', 'playbook_items'),
            ],
            'alternative_path' => [
                'summary' => $this->string($path['summary'] ?? '', 'alternative_path.summary'),
                'steps' => $this->alternativeSteps($path['steps'] ?? []),
            ],
            'outcome_analysis' => [
                'actual_outcome' => $this->string($outcome['actual_outcome'] ?? '', 'outcome_analysis.actual_outcome'),
                'quality_of_outcome' => $this->nullableScore($outcome['quality_of_outcome'] ?? null, 'outcome_analysis.quality_of_outcome'),
                'was_best_possible_outcome_reached' => $this->boolean($outcome['was_best_possible_outcome_reached'] ?? false, 'outcome_analysis.was_best_possible_outcome_reached'),
                'why' => $this->string($outcome['why'] ?? '', 'outcome_analysis.why'),
                'what_could_have_improved_outcome' => $this->stringList($outcome['what_could_have_improved_outcome'] ?? [], 'outcome_analysis.what_could_have_improved_outcome'),
            ],
            'sales_stage_map' => $this->salesStages($payload['sales_stage_map']),
            'conversation_metrics' => SalesAnalysisSchema::emptyConversationMetrics(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $path): array
    {
        if (! is_array($value)) {
            throw new PermanentAnalysisException("{$path} must be an object.");
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function capped(array $items, string $path, string $limitKey): array
    {
        $max = SalesAnalysisSchema::LIMITS[$limitKey] ?? 12;
        $count = count($items);

        if ($count > $max * 2) {
            throw new PermanentAnalysisException("{$path} has too many items.");
        }

        return array_values(array_slice($items, 0, $max));
    }

    private function timestamp(mixed $value, string $path): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new PermanentAnalysisException("{$path} must be a number or null.");
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new PermanentAnalysisException("{$path} must not be negative.");
        }

        return $number;
    }

    private function nullableScore(mixed $value, string $path): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->score($value, $path);
    }

    private function nullableNonNegativeInt(mixed $value, string $path): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_float($value) && $value >= 0 && floor($value) === $value) {
            return (int) $value;
        }

        throw new PermanentAnalysisException("{$path} must be a non-negative integer or null.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moments(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'timestamp_seconds' => null,
                    'speaker' => null,
                    'explanation' => $this->string($item, $path.'.'.$index),
                    'quote' => null,
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $speaker = $item['speaker'] ?? null;
            if ($speaker !== null && ! is_numeric($speaker)) {
                throw new PermanentAnalysisException("{$path}.{$index}.speaker must be an integer or null.");
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, $path.'.'.$index.'.timestamp_seconds'),
                'speaker' => $speaker === null ? null : (int) $speaker,
                'explanation' => $this->string($item['explanation'] ?? $item['text'] ?? '', $path.'.'.$index.'.explanation'),
                'quote' => isset($item['quote']) && $item['quote'] !== null ? $this->string($item['quote'], $path.'.'.$index.'.quote') : null,
            ];
        }

        return $this->capped($normalized, $path, 'control_moments');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function signals(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'timestamp_seconds' => null,
                    'speaker' => null,
                    'signal' => $this->string($item, $path.'.'.$index),
                    'why_it_matters' => '',
                    'quote' => null,
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $speaker = $item['speaker'] ?? null;
            if ($speaker !== null && ! is_numeric($speaker)) {
                throw new PermanentAnalysisException("{$path}.{$index}.speaker must be an integer or null.");
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, $path.'.'.$index.'.timestamp_seconds'),
                'speaker' => $speaker === null ? null : (int) $speaker,
                'signal' => $this->string($item['signal'] ?? $item['text'] ?? '', $path.'.'.$index.'.signal'),
                'why_it_matters' => $this->string($item['why_it_matters'] ?? '', $path.'.'.$index.'.why_it_matters'),
                'quote' => isset($item['quote']) && $item['quote'] !== null ? $this->string($item['quote'], $path.'.'.$index.'.quote') : null,
            ];
        }

        return $this->capped($normalized, $path, 'customer_signals');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missedSignals(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('missed_signals must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("missed_signals.{$index} must be an object.");
            }

            $signal = $this->string($item['signal'] ?? '', "missed_signals.{$index}.signal");
            if ($this->blank($signal)) {
                continue;
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, "missed_signals.{$index}.timestamp_seconds"),
                'signal' => $signal,
                'seller_response_quality' => $this->enum($item['seller_response_quality'] ?? 'missed', SalesAnalysisSchema::SELLER_RESPONSE_QUALITY, "missed_signals.{$index}.seller_response_quality"),
                'impact' => $this->enum($item['impact'] ?? 'medium', SalesAnalysisSchema::IMPACT, "missed_signals.{$index}.impact"),
                'recommended_action' => $this->string($item['recommended_action'] ?? '', "missed_signals.{$index}.recommended_action"),
                'better_response' => $this->string($item['better_response'] ?? '', "missed_signals.{$index}.better_response"),
            ];
        }

        return $this->capped($normalized, 'missed_signals', 'missed_signals');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questions(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'timestamp_seconds' => null,
                    'question' => $this->string($item, $path.'.'.$index),
                    'assessment' => '',
                    'better_version' => null,
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, $path.'.'.$index.'.timestamp_seconds'),
                'question' => $this->string($item['question'] ?? $item['text'] ?? '', $path.'.'.$index.'.question'),
                'assessment' => $this->string($item['assessment'] ?? '', $path.'.'.$index.'.assessment'),
                'better_version' => isset($item['better_version']) && $item['better_version'] !== null
                    ? $this->string($item['better_version'], $path.'.'.$index.'.better_version')
                    : null,
            ];
        }

        return $this->capped($normalized, $path, 'open_questions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function objectionMap(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('objection_map must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("objection_map.{$index} must be an object.");
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, "objection_map.{$index}.timestamp_seconds"),
                'objection' => $this->string($item['objection'] ?? '', "objection_map.{$index}.objection"),
                'category' => $this->enum($item['category'] ?? 'other', SalesAnalysisSchema::OBJECTION_CATEGORIES, "objection_map.{$index}.category"),
                'explicit_or_implicit' => $this->enum($item['explicit_or_implicit'] ?? 'explicit', SalesAnalysisSchema::EXPLICITNESS, "objection_map.{$index}.explicit_or_implicit"),
                'seller_response' => $this->string($item['seller_response'] ?? '', "objection_map.{$index}.seller_response"),
                'response_quality' => $this->nullableScore($item['response_quality'] ?? null, "objection_map.{$index}.response_quality"),
                'what_was_good' => $this->string($item['what_was_good'] ?? '', "objection_map.{$index}.what_was_good"),
                'what_was_missing' => $this->string($item['what_was_missing'] ?? '', "objection_map.{$index}.what_was_missing"),
                'better_response' => $this->string($item['better_response'] ?? '', "objection_map.{$index}.better_response"),
                'resolved' => $this->boolean($item['resolved'] ?? false, "objection_map.{$index}.resolved"),
            ];
        }

        return $this->capped($normalized, 'objection_map', 'objection_map');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('timeline must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("timeline.{$index} must be an object.");
            }

            $title = $this->string($item['title'] ?? $item['text'] ?? '', "timeline.{$index}.title");
            if ($this->blank($title)) {
                continue;
            }

            $type = $item['type'] ?? $item['event_type'] ?? '';
            if (! is_string($type) || ! in_array($type, SalesAnalysisSchema::TIMELINE_TYPES, true)) {
                continue;
            }

            $speaker = $item['speaker'] ?? null;
            if ($speaker !== null && ! is_numeric($speaker)) {
                throw new PermanentAnalysisException("timeline.{$index}.speaker must be an integer or null.");
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, "timeline.{$index}.timestamp_seconds"),
                'type' => $type,
                'title' => $title,
                'description' => $this->string($item['description'] ?? '', "timeline.{$index}.description"),
                'speaker' => $speaker === null ? null : (int) $speaker,
                'quote' => isset($item['quote']) && $item['quote'] !== null ? $this->string($item['quote'], "timeline.{$index}.quote") : null,
            ];
        }

        return $this->capped($normalized, 'timeline', 'timeline');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function turningPoints(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('turning_points must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("turning_points.{$index} must be an object.");
            }

            $whatChanged = $this->string($item['what_changed'] ?? '', "turning_points.{$index}.what_changed");
            if ($this->blank($whatChanged)) {
                continue;
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, "turning_points.{$index}.timestamp_seconds"),
                'what_changed' => $whatChanged,
                'before' => $this->string($item['before'] ?? '', "turning_points.{$index}.before"),
                'after' => $this->string($item['after'] ?? '', "turning_points.{$index}.after"),
                'impact' => $this->enum($item['impact'] ?? 'medium', SalesAnalysisSchema::IMPACT, "turning_points.{$index}.impact"),
            ];
        }

        return $this->capped($normalized, 'turning_points', 'turning_points');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalMistakes(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('critical_mistakes must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("critical_mistakes.{$index} must be an object.");
            }

            $mistake = $this->string($item['mistake'] ?? '', "critical_mistakes.{$index}.mistake");
            if ($this->blank($mistake)) {
                continue;
            }

            $normalized[] = [
                'timestamp_seconds' => $this->timestamp($item['timestamp_seconds'] ?? null, "critical_mistakes.{$index}.timestamp_seconds"),
                'mistake' => $mistake,
                'impact' => $this->enum($item['impact'] ?? 'high', SalesAnalysisSchema::IMPACT, "critical_mistakes.{$index}.impact"),
                'why' => $this->string($item['why'] ?? '', "critical_mistakes.{$index}.why"),
                'better_action' => $this->string($item['better_action'] ?? '', "critical_mistakes.{$index}.better_action"),
                'example_phrase' => $this->string($item['example_phrase'] ?? '', "critical_mistakes.{$index}.example_phrase"),
            ];
        }

        return $this->capped($normalized, 'critical_mistakes', 'critical_mistakes');
    }

    /**
     * @return array<int, array{text:string, why:string}>
     */
    private function practices(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $text = $this->string($item, $path.'.'.$index);
                if ($this->blank($text)) {
                    continue;
                }

                $normalized[] = [
                    'text' => $text,
                    'why' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $text = $this->string($item['text'] ?? $item['practice'] ?? '', $path.'.'.$index.'.text');
            if ($this->blank($text)) {
                continue;
            }

            $normalized[] = [
                'text' => $text,
                'why' => $this->string($item['why'] ?? '', $path.'.'.$index.'.why'),
            ];
        }

        return $this->capped($normalized, $path, $path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coachingPriorities(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('coaching_priorities must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("coaching_priorities.{$index} must be an object.");
            }

            $skill = $this->string($item['skill'] ?? '', "coaching_priorities.{$index}.skill");
            if ($this->blank($skill)) {
                continue;
            }

            $priority = $item['priority'] ?? ($index + 1);
            if (! is_int($priority) && ! (is_float($priority) && floor($priority) === $priority)) {
                throw new PermanentAnalysisException("coaching_priorities.{$index}.priority must be an integer.");
            }
            $priority = (int) $priority;
            if ($priority < 1 || $priority > 5) {
                throw new PermanentAnalysisException("coaching_priorities.{$index}.priority must be between 1 and 5.");
            }

            $evidence = $item['evidence'] ?? [];
            if (! is_array($evidence)) {
                throw new PermanentAnalysisException("coaching_priorities.{$index}.evidence must be an array.");
            }

            $normalized[] = [
                'priority' => $priority,
                'skill' => $skill,
                'why' => $this->string($item['why'] ?? '', "coaching_priorities.{$index}.why"),
                'evidence' => $this->stringList($evidence, "coaching_priorities.{$index}.evidence"),
                'practice' => $this->string($item['practice'] ?? '', "coaching_priorities.{$index}.practice"),
                'success_criteria' => $this->string($item['success_criteria'] ?? '', "coaching_priorities.{$index}.success_criteria"),
            ];
        }

        usort($normalized, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $this->capped($normalized, 'coaching_priorities', 'coaching_priorities');
    }

    /**
     * @return array<int, array{stage:string, what_to_do:string, example_phrase:string}>
     */
    private function alternativeSteps(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('alternative_path.steps must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("alternative_path.steps.{$index} must be an object.");
            }

            $stage = $this->string($item['stage'] ?? '', "alternative_path.steps.{$index}.stage");
            $what = $this->string($item['what_to_do'] ?? '', "alternative_path.steps.{$index}.what_to_do");
            if ($this->blank($stage) && $this->blank($what)) {
                continue;
            }

            $normalized[] = [
                'stage' => $stage,
                'what_to_do' => $what,
                'example_phrase' => $this->string($item['example_phrase'] ?? '', "alternative_path.steps.{$index}.example_phrase"),
            ];
        }

        return $this->capped($normalized, 'alternative_path.steps', 'alternative_path_steps');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salesStages(mixed $items): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException('sales_stage_map must be an array.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw new PermanentAnalysisException("sales_stage_map.{$index} must be an object.");
            }

            $start = $this->timestamp($item['start_seconds'] ?? null, "sales_stage_map.{$index}.start_seconds");
            $end = $this->timestamp($item['end_seconds'] ?? null, "sales_stage_map.{$index}.end_seconds");
            if ($start !== null && $end !== null && $end < $start) {
                throw new PermanentAnalysisException("sales_stage_map.{$index} end_seconds must be greater than or equal to start_seconds.");
            }

            $normalized[] = [
                'stage' => $this->enum($item['stage'] ?? 'other', SalesAnalysisSchema::SALES_STAGES, "sales_stage_map.{$index}.stage"),
                'start_seconds' => $start,
                'end_seconds' => $end,
                'quality' => $this->nullableScore($item['quality'] ?? null, "sales_stage_map.{$index}.quality"),
                'summary' => $this->string($item['summary'] ?? '', "sales_stage_map.{$index}.summary"),
            ];
        }

        return $this->capped($normalized, 'sales_stage_map', 'sales_stage_map');
    }

    private function blank(string $value): bool
    {
        return trim($value) === '';
    }
}
