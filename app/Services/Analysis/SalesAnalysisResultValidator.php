<?php

namespace App\Services\Analysis;

use App\Exceptions\Analysis\PermanentAnalysisException;
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
        foreach ([
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
        ] as $key) {
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
            'speaker_roles' => $roles,
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
        $total = app(CompanyScoreCalculator::class)->total($criteria, $context->scorecardSnapshot ?? ['criteria' => []]);

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
                'violations' => $this->findings($claims['violations'] ?? [], 'company_specific.forbidden_claims.violations'),
            ],
            'objection_handling' => [
                'matched' => $this->matchedObjections($objections['matched'] ?? []),
            ],
            'offering_accuracy' => [
                'issues' => $this->findings($offerings['issues'] ?? [], 'company_specific.offering_accuracy.issues'),
            ],
            'scorecard' => [
                'total_score' => $total,
                'criteria' => $criteria,
            ],
        ];
    }

    /**
     * @param  mixed  $items
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
            if (! is_array($item) || ! isset($item['key'])) {
                throw new PermanentAnalysisException("company_specific.scorecard.criteria.{$index} must include a key.");
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
                'score' => $applicable ? $this->boundedScore($item['score'] ?? null, $max, "company_specific.scorecard.criteria.{$index}.score") : null,
                'max_score' => $max,
                'applicable' => $applicable,
                'summary' => $this->string($item['summary'] ?? '', "company_specific.scorecard.criteria.{$index}.summary"),
                'evidence' => $this->findings($item['evidence'] ?? [], "company_specific.scorecard.criteria.{$index}.evidence"),
                'critical_failure' => $this->boolean($item['critical_failure'] ?? false, "company_specific.scorecard.criteria.{$index}.critical_failure"),
            ];
        }

        foreach ($expected as $key => $criterion) {
            if (! isset($seen[$key])) {
                $normalized[] = [
                    'key' => $key,
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
     * @param  mixed  $items
     * @return array<int, string>
     */
    private function stringList(mixed $items, string $path): array
    {
        if (! is_array($items)) {
            throw new PermanentAnalysisException("{$path} must be an array.");
        }

        $values = [];
        foreach (array_values($items) as $index => $item) {
            $values[] = $this->string($item, $path.'.'.$index);
        }

        return $values;
    }

    /**
     * @param  mixed  $items
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
            $normalized[] = $this->finding($item, $path.'.'.$index);
        }

        return $normalized;
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

        $timestamp = $item['timestamp_seconds'] ?? null;
        if ($timestamp !== null && ! is_numeric($timestamp)) {
            throw new PermanentAnalysisException("{$path}.timestamp_seconds must be a number or null.");
        }

        return [
            'text' => $this->string($item['text'], $path.'.text'),
            'speaker' => $speaker === null ? null : (int) $speaker,
            'timestamp_seconds' => $timestamp === null ? null : (float) $timestamp,
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
                $normalized[] = [
                    'original' => '',
                    'suggested' => $this->string($item, $path.'.'.$index),
                    'reason' => '',
                ];

                continue;
            }

            if (! is_array($item)) {
                throw new PermanentAnalysisException("{$path}.{$index} must be an object.");
            }

            $normalized[] = [
                'original' => $this->string($item['original'] ?? '', $path.'.'.$index.'.original'),
                'suggested' => $this->string($item['suggested'] ?? '', $path.'.'.$index.'.suggested'),
                'reason' => $this->string($item['reason'] ?? '', $path.'.'.$index.'.reason'),
            ];
        }

        return $normalized;
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
}
