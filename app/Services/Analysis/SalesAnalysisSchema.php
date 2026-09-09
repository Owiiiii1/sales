<?php

namespace App\Services\Analysis;

final class SalesAnalysisSchema
{
    public const VERSION = 1;

    public const SECTION_KEYS = [
        'opening_rapport',
        'discovery_needs',
        'questions_listening',
        'presentation_value',
        'objections',
        'pricing_negotiation',
        'closing_next_step',
    ];

    public const SECTION_TITLES = [
        'opening_rapport' => 'Opening & Rapport',
        'discovery_needs' => 'Discovery & Needs',
        'questions_listening' => 'Questions & Listening',
        'presentation_value' => 'Presentation & Value',
        'objections' => 'Objections',
        'pricing_negotiation' => 'Pricing / Negotiation',
        'closing_next_step' => 'Closing & Next Step',
    ];

    public const OUTCOMES = [
        'sale',
        'appointment',
        'follow_up',
        'proposal',
        'interested',
        'not_interested',
        'lost',
        'unresolved',
        'unknown',
    ];

    public const INTENTS = [
        'high',
        'medium',
        'low',
        'unknown',
    ];

    public const SPEAKER_ROLES = [
        'seller',
        'customer',
        'unknown',
        'other',
    ];

    /**
     * JSON Schema used when a provider supports native structured output.
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        $finding = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'text' => ['type' => 'string'],
                'speaker' => ['type' => ['integer', 'null']],
                'timestamp_seconds' => ['type' => ['number', 'null']],
                'quote' => ['type' => ['string', 'null']],
            ],
            'required' => ['text'],
        ];

        $section = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'applicable' => ['type' => 'boolean'],
                'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                'summary' => ['type' => 'string'],
                'strengths' => ['type' => 'array', 'items' => $finding],
                'issues' => ['type' => 'array', 'items' => $finding],
            ],
            'required' => ['applicable', 'summary', 'strengths', 'issues'],
        ];

        $sections = [];
        foreach (self::SECTION_KEYS as $key) {
            $sections[$key] = $section;
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
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
            ],
            'properties' => [
                'overall_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'summary' => ['type' => 'string'],
                'call_outcome' => ['type' => 'string', 'enum' => self::OUTCOMES],
                'customer_intent' => ['type' => 'string', 'enum' => self::INTENTS],
                'speaker_roles' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string', 'enum' => self::SPEAKER_ROLES],
                ],
                'sections' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::SECTION_KEYS,
                    'properties' => $sections,
                ],
                'strengths' => ['type' => 'array', 'items' => $finding],
                'weaknesses' => ['type' => 'array', 'items' => $finding],
                'missed_opportunities' => ['type' => 'array', 'items' => $finding],
                'buying_signals' => ['type' => 'array', 'items' => $finding],
                'objections_detected' => ['type' => 'array', 'items' => $finding],
                'recommendations' => ['type' => 'array', 'items' => $finding],
                'better_phrases' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'original' => ['type' => 'string'],
                            'suggested' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['original', 'suggested'],
                    ],
                ],
                'next_step' => ['type' => 'string'],
            ],
        ];
    }
}
