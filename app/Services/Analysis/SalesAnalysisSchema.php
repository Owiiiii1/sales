<?php

namespace App\Services\Analysis;

final class SalesAnalysisSchema
{
    public const VERSION = 3;

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

    public const CONFIDENCE = [
        'high',
        'medium',
        'low',
    ];

    public const OBJECTIVE_ALIGNMENT = [
        'aligned',
        'partially_aligned',
        'misaligned',
        'unknown',
    ];

    public const WHO_LED = [
        'seller',
        'customer',
        'balanced',
        'unclear',
    ];

    public const IMPACT = [
        'high',
        'medium',
        'low',
    ];

    public const SELLER_RESPONSE_QUALITY = [
        'missed',
        'weak',
        'good',
    ];

    public const OBJECTION_CATEGORIES = [
        'price',
        'timing',
        'trust',
        'competitor',
        'authority',
        'need',
        'risk',
        'implementation',
        'other',
    ];

    public const EXPLICITNESS = [
        'explicit',
        'implicit',
    ];

    public const NEXT_STEP_SPECIFICITY = [
        'specific',
        'vague',
        'none',
    ];

    public const COMMITMENT = [
        'high',
        'medium',
        'low',
        'none',
    ];

    public const TIMELINE_TYPES = [
        'positive',
        'warning',
        'critical',
        'turning_point',
        'objection',
        'buying_signal',
        'missed_opportunity',
    ];

    public const SALES_STAGES = [
        'opening',
        'discovery',
        'presentation',
        'objection',
        'negotiation',
        'closing',
        'other',
    ];

    public const V3_REQUIRED = [
        'executive_summary',
        'call_objective',
        'conversation_control',
        'customer_signals',
        'missed_signals',
        'discovery_depth',
        'question_analysis',
        'listening',
        'value_communication',
        'objection_map',
        'negotiation',
        'trust_rapport',
        'closing',
        'timeline',
        'turning_points',
        'critical_mistakes',
        'what_to_repeat',
        'what_to_stop',
        'what_to_start',
        'coaching_priorities',
        'next_call_playbook',
        'alternative_path',
        'outcome_analysis',
        'sales_stage_map',
        'customer_intent_confidence',
    ];

    public const LIMITS = [
        'timeline' => 15,
        'critical_mistakes' => 7,
        'coaching_priorities' => 5,
        'better_phrases' => 10,
        'missed_signals' => 10,
        'turning_points' => 7,
        'customer_signals' => 10,
        'objection_map' => 12,
        'what_to_repeat' => 8,
        'what_to_stop' => 8,
        'what_to_start' => 8,
        'sales_stage_map' => 10,
        'alternative_path_steps' => 8,
        'findings' => 12,
        'playbook_items' => 8,
        'open_questions' => 10,
        'closed_questions' => 10,
        'control_moments' => 8,
    ];

    /**
     * JSON Schema used when a provider supports native structured output.
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        $finding = self::findingSchema();
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

        $signal = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'timestamp_seconds' => ['type' => ['number', 'null']],
                'speaker' => ['type' => ['integer', 'null']],
                'signal' => ['type' => 'string'],
                'why_it_matters' => ['type' => 'string'],
                'quote' => ['type' => ['string', 'null']],
            ],
            'required' => ['signal', 'why_it_matters'],
        ];

        $moment = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'timestamp_seconds' => ['type' => ['number', 'null']],
                'speaker' => ['type' => ['integer', 'null']],
                'explanation' => ['type' => 'string'],
                'quote' => ['type' => ['string', 'null']],
            ],
            'required' => ['explanation'],
        ];

        $question = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'timestamp_seconds' => ['type' => ['number', 'null']],
                'question' => ['type' => 'string'],
                'assessment' => ['type' => 'string'],
                'better_version' => ['type' => ['string', 'null']],
            ],
            'required' => ['question'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_values(array_unique(array_merge([
                'overall_score',
                'summary',
                'call_outcome',
                'customer_intent',
                'customer_intent_confidence',
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
                'company_context_used',
                'company_specific',
            ], self::V3_REQUIRED))),
            'properties' => [
                'overall_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'summary' => ['type' => 'string'],
                'call_outcome' => ['type' => 'string', 'enum' => self::OUTCOMES],
                'customer_intent' => ['type' => 'string', 'enum' => self::INTENTS],
                'customer_intent_confidence' => ['type' => 'string', 'enum' => self::CONFIDENCE],
                'speaker_roles' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string', 'enum' => self::SPEAKER_ROLES],
                ],
                'speaker_roles_confidence' => ['type' => 'string', 'enum' => self::CONFIDENCE],
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
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'original' => ['type' => 'string'],
                            'problem' => ['type' => 'string'],
                            'better' => ['type' => 'string'],
                            'suggested' => ['type' => 'string'],
                            'why_better' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['original'],
                    ],
                ],
                'next_step' => ['type' => 'string'],
                'company_context_used' => ['type' => 'boolean'],
                'company_specific' => self::companySpecificSchema($finding),
                'executive_summary' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['one_sentence', 'what_happened', 'why_it_ended_this_way', 'biggest_strength', 'biggest_problem', 'best_next_action'],
                    'properties' => [
                        'one_sentence' => ['type' => 'string'],
                        'what_happened' => ['type' => 'string'],
                        'why_it_ended_this_way' => ['type' => 'string'],
                        'biggest_strength' => ['type' => 'string'],
                        'biggest_problem' => ['type' => 'string'],
                        'best_next_action' => ['type' => 'string'],
                    ],
                ],
                'call_objective' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['seller_objective', 'customer_objective', 'objective_alignment', 'summary'],
                    'properties' => [
                        'seller_objective' => ['type' => 'string'],
                        'customer_objective' => ['type' => 'string'],
                        'objective_alignment' => ['type' => 'string', 'enum' => self::OBJECTIVE_ALIGNMENT],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'conversation_control' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'who_led', 'summary', 'loss_of_control_moments', 'recovery_moments'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'who_led' => ['type' => 'string', 'enum' => self::WHO_LED],
                        'summary' => ['type' => 'string'],
                        'loss_of_control_moments' => ['type' => 'array', 'items' => $moment],
                        'recovery_moments' => ['type' => 'array', 'items' => $moment],
                    ],
                ],
                'customer_signals' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['positive_signals', 'negative_signals', 'buying_signals', 'hesitation_signals', 'trust_signals', 'risk_signals'],
                    'properties' => [
                        'positive_signals' => ['type' => 'array', 'items' => $signal],
                        'negative_signals' => ['type' => 'array', 'items' => $signal],
                        'buying_signals' => ['type' => 'array', 'items' => $signal],
                        'hesitation_signals' => ['type' => 'array', 'items' => $signal],
                        'trust_signals' => ['type' => 'array', 'items' => $signal],
                        'risk_signals' => ['type' => 'array', 'items' => $signal],
                    ],
                ],
                'missed_signals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'signal' => ['type' => 'string'],
                            'seller_response_quality' => ['type' => 'string', 'enum' => self::SELLER_RESPONSE_QUALITY],
                            'impact' => ['type' => 'string', 'enum' => self::IMPACT],
                            'recommended_action' => ['type' => 'string'],
                            'better_response' => ['type' => 'string'],
                        ],
                        'required' => ['signal', 'seller_response_quality', 'impact'],
                    ],
                ],
                'discovery_depth' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'needs_discovered', 'needs_not_explored', 'pain_points', 'business_impact_discussed', 'decision_criteria_found', 'budget_discussed', 'timeline_discussed', 'decision_process_discussed', 'summary'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'needs_discovered' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'needs_not_explored' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'pain_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'business_impact_discussed' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'decision_criteria_found' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'budget_discussed' => ['type' => 'boolean'],
                        'timeline_discussed' => ['type' => 'boolean'],
                        'decision_process_discussed' => ['type' => 'boolean'],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'question_analysis' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['total_questions_estimate', 'open_questions', 'closed_questions', 'strong_questions', 'weak_questions', 'missed_questions', 'question_sequence_quality', 'summary'],
                    'properties' => [
                        'total_questions_estimate' => ['type' => ['integer', 'null'], 'minimum' => 0],
                        'open_questions' => ['type' => 'array', 'items' => $question],
                        'closed_questions' => ['type' => 'array', 'items' => $question],
                        'strong_questions' => ['type' => 'array', 'items' => $question],
                        'weak_questions' => ['type' => 'array', 'items' => $question],
                        'missed_questions' => ['type' => 'array', 'items' => $question],
                        'question_sequence_quality' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'listening' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'summary', 'good_listening_moments', 'interruptions_or_ignored_points', 'follow_up_quality', 'paraphrasing_quality'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'summary' => ['type' => 'string'],
                        'good_listening_moments' => ['type' => 'array', 'items' => $finding],
                        'interruptions_or_ignored_points' => ['type' => 'array', 'items' => $finding],
                        'follow_up_quality' => ['type' => 'array', 'items' => $finding],
                        'paraphrasing_quality' => ['type' => 'string'],
                    ],
                ],
                'value_communication' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'features_mentioned', 'benefits_mentioned', 'value_links_to_customer_needs', 'generic_pitch_moments', 'strong_value_moments', 'summary'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'features_mentioned' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'benefits_mentioned' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'value_links_to_customer_needs' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'generic_pitch_moments' => ['type' => 'array', 'items' => $finding],
                        'strong_value_moments' => ['type' => 'array', 'items' => $finding],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'objection_map' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'objection' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => self::OBJECTION_CATEGORIES],
                            'explicit_or_implicit' => ['type' => 'string', 'enum' => self::EXPLICITNESS],
                            'seller_response' => ['type' => 'string'],
                            'response_quality' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'what_was_good' => ['type' => 'string'],
                            'what_was_missing' => ['type' => 'string'],
                            'better_response' => ['type' => 'string'],
                            'resolved' => ['type' => 'boolean'],
                        ],
                        'required' => ['objection', 'category', 'explicit_or_implicit', 'resolved'],
                    ],
                ],
                'negotiation' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['applicable', 'price_discussed', 'discount_discussed', 'summary'],
                    'properties' => [
                        'applicable' => ['type' => 'boolean'],
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'price_discussed' => ['type' => 'boolean'],
                        'discount_discussed' => ['type' => 'boolean'],
                        'seller_defended_value' => ['type' => 'string'],
                        'concessions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'trust_rapport' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'summary', 'trust_building_moments', 'trust_reducing_moments', 'tone_assessment'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'summary' => ['type' => 'string'],
                        'trust_building_moments' => ['type' => 'array', 'items' => $finding],
                        'trust_reducing_moments' => ['type' => 'array', 'items' => $finding],
                        'tone_assessment' => ['type' => 'string'],
                    ],
                ],
                'closing' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['score', 'next_step_defined', 'next_step_specificity', 'commitment_level', 'summary', 'missed_closing_opportunities', 'better_closing'],
                    'properties' => [
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'next_step_defined' => ['type' => 'boolean'],
                        'next_step_specificity' => ['type' => 'string', 'enum' => self::NEXT_STEP_SPECIFICITY],
                        'commitment_level' => ['type' => 'string', 'enum' => self::COMMITMENT],
                        'summary' => ['type' => 'string'],
                        'missed_closing_opportunities' => ['type' => 'array', 'items' => $finding],
                        'better_closing' => ['type' => 'string'],
                    ],
                ],
                'timeline' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'type' => ['type' => 'string', 'enum' => self::TIMELINE_TYPES],
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'speaker' => ['type' => ['integer', 'null']],
                            'quote' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['type', 'title'],
                    ],
                ],
                'turning_points' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'what_changed' => ['type' => 'string'],
                            'before' => ['type' => 'string'],
                            'after' => ['type' => 'string'],
                            'impact' => ['type' => 'string', 'enum' => self::IMPACT],
                        ],
                        'required' => ['what_changed', 'impact'],
                    ],
                ],
                'critical_mistakes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'timestamp_seconds' => ['type' => ['number', 'null']],
                            'mistake' => ['type' => 'string'],
                            'impact' => ['type' => 'string', 'enum' => self::IMPACT],
                            'why' => ['type' => 'string'],
                            'better_action' => ['type' => 'string'],
                            'example_phrase' => ['type' => 'string'],
                        ],
                        'required' => ['mistake', 'impact'],
                    ],
                ],
                'what_to_repeat' => ['type' => 'array', 'items' => self::practiceSchema()],
                'what_to_stop' => ['type' => 'array', 'items' => self::practiceSchema()],
                'what_to_start' => ['type' => 'array', 'items' => self::practiceSchema()],
                'coaching_priorities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                            'skill' => ['type' => 'string'],
                            'why' => ['type' => 'string'],
                            'evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'practice' => ['type' => 'string'],
                            'success_criteria' => ['type' => 'string'],
                        ],
                        'required' => ['priority', 'skill', 'why', 'practice'],
                    ],
                ],
                'next_call_playbook' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['before_call', 'during_call', 'closing', 'follow_up'],
                    'properties' => [
                        'before_call' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'during_call' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'closing' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'follow_up' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'alternative_path' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['summary', 'steps'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'steps' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'stage' => ['type' => 'string'],
                                    'what_to_do' => ['type' => 'string'],
                                    'example_phrase' => ['type' => 'string'],
                                ],
                                'required' => ['stage', 'what_to_do'],
                            ],
                        ],
                    ],
                ],
                'outcome_analysis' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['actual_outcome', 'was_best_possible_outcome_reached', 'why', 'what_could_have_improved_outcome'],
                    'properties' => [
                        'actual_outcome' => ['type' => 'string'],
                        'quality_of_outcome' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'was_best_possible_outcome_reached' => ['type' => 'boolean'],
                        'why' => ['type' => 'string'],
                        'what_could_have_improved_outcome' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'sales_stage_map' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'stage' => ['type' => 'string', 'enum' => self::SALES_STAGES],
                            'start_seconds' => ['type' => ['number', 'null']],
                            'end_seconds' => ['type' => ['number', 'null']],
                            'quality' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'summary' => ['type' => 'string'],
                        ],
                        'required' => ['stage'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function findingSchema(): array
    {
        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    private static function practiceSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'text' => ['type' => 'string'],
                'why' => ['type' => 'string'],
            ],
            'required' => ['text'],
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private static function companySpecificSchema(array $finding): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'script_adherence',
                'mandatory_questions',
                'forbidden_claims',
                'objection_handling',
                'offering_accuracy',
                'scorecard',
            ],
            'properties' => [
                'script_adherence' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'applicable' => ['type' => 'boolean'],
                        'score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'summary' => ['type' => 'string'],
                        'issues' => ['type' => 'array', 'items' => $finding],
                    ],
                    'required' => ['applicable', 'summary', 'issues'],
                ],
                'mandatory_questions' => [
                    'type' => 'object',
                    'properties' => [
                        'asked' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'missed' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['asked', 'missed'],
                ],
                'forbidden_claims' => [
                    'type' => 'object',
                    'properties' => [
                        'violations' => ['type' => 'array', 'items' => $finding],
                    ],
                    'required' => ['violations'],
                ],
                'objection_handling' => [
                    'type' => 'object',
                    'properties' => [
                        'matched' => ['type' => 'array'],
                    ],
                    'required' => ['matched'],
                ],
                'offering_accuracy' => [
                    'type' => 'object',
                    'properties' => [
                        'issues' => ['type' => 'array', 'items' => $finding],
                    ],
                    'required' => ['issues'],
                ],
                'scorecard' => [
                    'type' => 'object',
                    'properties' => [
                        'criteria' => ['type' => 'array'],
                    ],
                    'required' => ['criteria'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyCompanySpecific(): array
    {
        return [
            'script_adherence' => [
                'applicable' => false,
                'score' => null,
                'summary' => '',
                'issues' => [],
            ],
            'mandatory_questions' => [
                'asked' => [],
                'missed' => [],
            ],
            'forbidden_claims' => [
                'violations' => [],
            ],
            'objection_handling' => [
                'matched' => [],
            ],
            'offering_accuracy' => [
                'issues' => [],
            ],
            'scorecard' => [
                'total_score' => null,
                'weighted_score' => null,
                'triggered_caps' => [],
                'criteria' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyConversationMetrics(): array
    {
        return [
            'seller_talk_percent' => null,
            'customer_talk_percent' => null,
            'longest_seller_monologue_seconds' => null,
            'speaker_switches' => null,
            'call_duration_seconds' => null,
        ];
    }
}
