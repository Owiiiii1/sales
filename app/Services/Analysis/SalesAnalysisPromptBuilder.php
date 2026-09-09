<?php

namespace App\Services\Analysis;

use App\Models\Transcript;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Support\LanguageCode;

class SalesAnalysisPromptBuilder
{
    /**
     * @return array{system:string, user:string}
     */
    public function messages(Transcript $transcript, AnalysisContext $context): array
    {
        $language = LanguageCode::normalize($context->language ?: $transcript->language) ?: 'en';
        $languageName = match ($language) {
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            default => 'English',
        };

        $system = implode("\n", [
            'You are a sales-call analyst for Sales Analyzer.',
            'Analyze the transcript of one sales conversation using generic B2B/B2C sales methodology.',
            'You do not know this company\'s script, pricing rules, product catalog, competitors, USP, or forbidden words.',
            'Do not invent company-specific requirements. Do not pretend you have a knowledge base.',
            'Use only evidence that appears in the transcript. If something is not in the transcript, do not claim it happened.',
            'If a section is not relevant to this call (for example no price discussion), set applicable=false instead of scoring it poorly.',
            'Scores are integers 0–100. 0 is very poor, 100 is excellent, based on evidence.',
            'Map speakers to seller, customer, unknown, or other. If you are not confident, use unknown. Do not guess.',
            'Do not change or rewrite the original transcript. Speaker roles belong only in speaker_roles.',
            "The conversation language is {$languageName} ({$language}). Write summary, section summaries, findings, recommendations, better phrases, and next_step in {$languageName}.",
            'Do not translate the conversation. Quote the speakers in their original language.',
            'Evidence quotes must be short. Include speaker index and timestamp_seconds when the transcript provides them.',
            'call_outcome must be one of: sale, appointment, follow_up, proposal, interested, not_interested, lost, unresolved, unknown.',
            'customer_intent must be one of: high, medium, low, unknown. This is sales intent from the conversation, not psychological profiling.',
            'Return a single JSON object that matches the required schema. No markdown. No commentary.',
        ]);

        $user = $this->userPrompt($transcript, $context, $languageName);

        return ['system' => $system, 'user' => $user];
    }

    private function userPrompt(Transcript $transcript, AnalysisContext $context, string $languageName): string
    {
        $lines = [
            'Analyze this sales call.',
            'Output language: '.$languageName,
        ];

        if (filled($context->companyName)) {
            $lines[] = 'Company name (metadata only, not a knowledge base): '.$context->companyName;
        }

        $lines[] = 'Transcript language code: '.($transcript->language ?: 'unknown');
        $lines[] = 'Full transcript:';
        $lines[] = $transcript->raw_text ?: '(empty)';
        $lines[] = '';
        $lines[] = 'Diarized segments (do not mutate; use for evidence and speaker_roles):';

        foreach ($transcript->segments as $segment) {
            $start = number_format((float) $segment->start_seconds, 1, '.', '');
            $lines[] = sprintf(
                '[%s] Speaker %d: %s',
                $start,
                $segment->speaker,
                $segment->text,
            );
        }

        $lines[] = '';
        $lines[] = 'Required JSON keys: overall_score, summary, call_outcome, customer_intent, speaker_roles, sections, strengths, weaknesses, missed_opportunities, buying_signals, objections_detected, recommendations, better_phrases, next_step.';
        $lines[] = 'sections keys: '.implode(', ', SalesAnalysisSchema::SECTION_KEYS);
        $lines[] = 'Each section: {applicable, score, summary, strengths[], issues[]}.';
        $lines[] = 'Finding objects: {text, speaker, timestamp_seconds, quote}.';
        $lines[] = 'better_phrases objects: {original, suggested, reason}.';
        $lines[] = 'speaker_roles example: {"0":"seller","1":"customer"}.';

        return implode("\n", $lines);
    }
}
