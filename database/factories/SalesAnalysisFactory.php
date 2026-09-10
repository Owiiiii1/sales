<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\SalesAnalysis;
use App\Services\Analysis\SalesAnalysisSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesAnalysis>
 */
class SalesAnalysisFactory extends Factory
{
    protected $model = SalesAnalysis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = self::validPayload();

        return [
            'call_id' => Call::factory()->state(['status' => 'completed']),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'schema_version' => SalesAnalysisSchema::VERSION,
            'overall_score' => $payload['overall_score'],
            'summary' => $payload['summary'],
            'result' => $payload,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validPayload(array $overrides = []): array
    {
        $finding = static fn (string $text, int $speaker = 0, float $time = 1.0, string $quote = 'Hello'): array => [
            'text' => $text,
            'speaker' => $speaker,
            'timestamp_seconds' => $time,
            'quote' => $quote,
        ];

        $section = static fn (int $score, string $summary): array => [
            'applicable' => true,
            'score' => $score,
            'summary' => $summary,
            'strengths' => [$finding('Clear greeting.')],
            'issues' => [],
        ];

        $payload = [
            'overall_score' => 72,
            'summary' => 'The seller opened well and discovered a need, but closing was vague.',
            'call_outcome' => 'follow_up',
            'customer_intent' => 'medium',
            'customer_intent_confidence' => 'medium',
            'speaker_roles' => [
                '0' => 'seller',
                '1' => 'customer',
            ],
            'sections' => [
                'opening_rapport' => $section(80, 'Polite opening.'),
                'discovery_needs' => $section(70, 'Some discovery happened.'),
                'questions_listening' => $section(65, 'A few questions were asked.'),
                'presentation_value' => $section(68, 'Value was mentioned.'),
                'objections' => [
                    'applicable' => false,
                    'score' => null,
                    'summary' => 'No objections were raised.',
                    'strengths' => [],
                    'issues' => [],
                ],
                'pricing_negotiation' => [
                    'applicable' => false,
                    'score' => null,
                    'summary' => 'Price was not discussed.',
                    'strengths' => [],
                    'issues' => [],
                ],
                'closing_next_step' => $section(55, 'Next step was unclear.'),
            ],
            'strengths' => [$finding('The seller thanked the customer for their time.')],
            'weaknesses' => [$finding('The seller did not confirm a next meeting.', 0, 8.0, 'I will be in touch.')],
            'missed_opportunities' => [$finding('Budget was never asked.')],
            'buying_signals' => [$finding('The customer said they are interested.', 1, 4.0, 'I am interested in your offer.')],
            'objections_detected' => [],
            'recommendations' => [$finding('Agree a specific follow-up time before ending.')],
            'better_phrases' => [[
                'timestamp_seconds' => 8.0,
                'original' => 'I will be in touch.',
                'problem' => 'No time or owner was agreed.',
                'better' => 'Can we book a 20-minute call on Thursday at 10?',
                'suggested' => 'Can we book a 20-minute call on Thursday at 10?',
                'why_better' => 'A specific next step is easier to keep.',
                'reason' => 'A specific next step is easier to keep.',
            ]],
            'next_step' => 'Schedule a follow-up with a clear agenda.',
            'company_context_used' => false,
            'company_specific' => SalesAnalysisSchema::emptyCompanySpecific(),
            'executive_summary' => [
                'one_sentence' => 'The seller opened well, found interest, then left without a booked next step.',
                'what_happened' => 'The customer said they were interested. The seller promised to follow up later instead of locking a time.',
                'why_it_ended_this_way' => 'Interest was real, but the close was vague so the only outcome is an unscheduled follow-up.',
                'biggest_strength' => 'A polite, time-respecting opening.',
                'biggest_problem' => 'No calendar commitment before hanging up.',
                'best_next_action' => 'Email two specific time options today and confirm the customer’s main need.',
            ],
            'call_objective' => [
                'seller_objective' => 'Introduce the offer and keep the conversation going.',
                'customer_objective' => 'Understand whether the offer is relevant.',
                'objective_alignment' => 'partially_aligned',
                'summary' => 'Both wanted a conversation; only the customer stated interest clearly.',
            ],
            'conversation_control' => [
                'score' => 58,
                'who_led' => 'balanced',
                'summary' => 'The seller opened, then followed the customer’s interest without steering to a next step.',
                'loss_of_control_moments' => [[
                    'timestamp_seconds' => 8.0,
                    'speaker' => 0,
                    'explanation' => 'The seller ended on “I will be in touch” instead of proposing a time.',
                    'quote' => 'I will be in touch.',
                ]],
                'recovery_moments' => [],
            ],
            'customer_signals' => [
                'positive_signals' => [[
                    'timestamp_seconds' => 4.0,
                    'speaker' => 1,
                    'signal' => 'Customer said they are interested in the offer.',
                    'why_it_matters' => 'This is a buying-adjacent signal that deserved a concrete next step.',
                    'quote' => 'I am interested in your offer.',
                ]],
                'negative_signals' => [],
                'buying_signals' => [[
                    'timestamp_seconds' => 4.0,
                    'speaker' => 1,
                    'signal' => 'Explicit interest in the offer.',
                    'why_it_matters' => 'The customer invited a closer discussion.',
                    'quote' => 'I am interested in your offer.',
                ]],
                'hesitation_signals' => [],
                'trust_signals' => [],
                'risk_signals' => [],
            ],
            'missed_signals' => [[
                'timestamp_seconds' => 4.0,
                'signal' => 'Customer mentioned interest without being asked what they need next.',
                'seller_response_quality' => 'weak',
                'impact' => 'high',
                'recommended_action' => 'Ask what they want to evaluate and book a time to continue.',
                'better_response' => 'Great — what would you need to see to decide, and can we book Thursday at 10?',
            ]],
            'discovery_depth' => [
                'score' => 48,
                'needs_discovered' => ['The customer is interested in the offer.'],
                'needs_not_explored' => ['Why they are looking now', 'Budget'],
                'pain_points' => [],
                'business_impact_discussed' => [],
                'decision_criteria_found' => [],
                'budget_discussed' => false,
                'timeline_discussed' => false,
                'decision_process_discussed' => false,
                'summary' => 'Interest was stated; the seller did not explore why or what “interested” means.',
            ],
            'question_analysis' => [
                'total_questions_estimate' => 1,
                'open_questions' => [],
                'closed_questions' => [],
                'strong_questions' => [],
                'weak_questions' => [],
                'missed_questions' => [[
                    'timestamp_seconds' => 5.0,
                    'question' => 'What would make this offer a fit for you?',
                    'assessment' => 'Never asked after the interest signal.',
                    'better_version' => 'What would you need to see for this to be worth a next meeting?',
                ]],
                'question_sequence_quality' => 'Too few questions to sequence.',
                'summary' => 'The seller barely asked anything after the customer showed interest.',
            ],
            'listening' => [
                'score' => 70,
                'summary' => 'The seller heard the greeting and interest; no overlap evidence of interrupting.',
                'good_listening_moments' => [$finding('Allowed the customer to state interest.')],
                'interruptions_or_ignored_points' => [],
                'follow_up_quality' => [],
                'paraphrasing_quality' => 'No paraphrase of the customer’s interest.',
            ],
            'value_communication' => [
                'score' => 40,
                'features_mentioned' => [],
                'benefits_mentioned' => [],
                'value_links_to_customer_needs' => [],
                'generic_pitch_moments' => [],
                'strong_value_moments' => [],
                'summary' => 'The offer was named but not tied to a discovered need.',
            ],
            'objection_map' => [],
            'negotiation' => [
                'applicable' => false,
                'score' => null,
                'price_discussed' => false,
                'discount_discussed' => false,
                'seller_defended_value' => '',
                'concessions' => [],
                'risks' => [],
                'summary' => 'Price was not discussed.',
            ],
            'trust_rapport' => [
                'score' => 78,
                'summary' => 'Polite opening built basic rapport. Tone is judged from wording only.',
                'trust_building_moments' => [$finding('Thanked the customer for their time.')],
                'trust_reducing_moments' => [],
                'tone_assessment' => 'Wording is courteous; no voice-emotion model was used.',
            ],
            'closing' => [
                'score' => 40,
                'next_step_defined' => false,
                'next_step_specificity' => 'vague',
                'commitment_level' => 'low',
                'summary' => 'The seller promised to follow up without a date or owner.',
                'missed_closing_opportunities' => [$finding('After interest, no meeting was proposed.', 0, 8.0, 'I will be in touch.')],
                'better_closing' => 'Can we book 20 minutes Thursday at 10 to map what you need?',
            ],
            'timeline' => [
                [
                    'timestamp_seconds' => 0.0,
                    'type' => 'positive',
                    'title' => 'Polite opening',
                    'description' => 'Seller thanked the customer for their time.',
                    'speaker' => 0,
                    'quote' => 'Hello, thanks for taking the time.',
                ],
                [
                    'timestamp_seconds' => 4.0,
                    'type' => 'buying_signal',
                    'title' => 'Customer interest',
                    'description' => 'The customer said they are interested in the offer.',
                    'speaker' => 1,
                    'quote' => 'I am interested in your offer.',
                ],
                [
                    'timestamp_seconds' => 8.0,
                    'type' => 'missed_opportunity',
                    'title' => 'Vague close',
                    'description' => 'Seller left without booking a next step.',
                    'speaker' => 0,
                    'quote' => 'I will be in touch.',
                ],
            ],
            'turning_points' => [[
                'timestamp_seconds' => 8.0,
                'what_changed' => 'Interest was not converted into a booked next step.',
                'before' => 'Customer had stated interest.',
                'after' => 'The call ended on an unscheduled follow-up.',
                'impact' => 'high',
            ]],
            'critical_mistakes' => [[
                'timestamp_seconds' => 8.0,
                'mistake' => 'Closed with a vague “I will be in touch” after a buying signal.',
                'impact' => 'high',
                'why' => 'Interest decayed into no calendar commitment, so the likely outcome dropped from appointment to unscheduled follow-up.',
                'better_action' => 'Propose two times and confirm the evaluation agenda.',
                'example_phrase' => 'You mentioned interest — can we book Thursday at 10 or Friday at 14:00?',
            ]],
            'what_to_repeat' => [[
                'text' => 'Open by thanking the customer for their time.',
                'why' => 'The greeting was specific and courteous.',
            ]],
            'what_to_stop' => [[
                'text' => 'Ending on “I will be in touch” after interest.',
                'why' => 'It replaces a booked next step with an unowned promise.',
            ]],
            'what_to_start' => [[
                'text' => 'Ask what “interested” means, then offer two meeting times.',
                'why' => 'The interest signal was left unexplored.',
            ]],
            'coaching_priorities' => [[
                'priority' => 1,
                'skill' => 'Closing a next step',
                'why' => 'Interest was explicit and still not booked.',
                'evidence' => ['I will be in touch.', 'I am interested in your offer.'],
                'practice' => 'After any interest line, propose two times before wrapping up.',
                'success_criteria' => 'The call ends with a dated next meeting or a clear customer decline.',
            ]],
            'next_call_playbook' => [
                'before_call' => ['Prepare two calendar slots and one discovery question about why they are looking.'],
                'during_call' => ['After interest, ask what they need to evaluate, then offer times.'],
                'closing' => ['Do not leave until a time is booked or the customer declines a time.'],
                'follow_up' => ['Send the two time options in writing the same day.'],
            ],
            'alternative_path' => [
                'summary' => 'Keep the polite open, explore the interest, and book a 20-minute follow-up.',
                'steps' => [
                    [
                        'stage' => 'opening',
                        'what_to_do' => 'Keep the thank-you and confirm time available.',
                        'example_phrase' => 'Thanks for taking the time — is now still a good moment?',
                    ],
                    [
                        'stage' => 'discovery',
                        'what_to_do' => 'Ask what interested them.',
                        'example_phrase' => 'You said you are interested — what would you want this to solve?',
                    ],
                    [
                        'stage' => 'closing',
                        'what_to_do' => 'Book a specific next step.',
                        'example_phrase' => 'Can we book Thursday at 10 to go through that?',
                    ],
                ],
            ],
            'outcome_analysis' => [
                'actual_outcome' => 'follow_up',
                'quality_of_outcome' => 45,
                'was_best_possible_outcome_reached' => false,
                'why' => 'An appointment was available after explicit interest; the seller did not ask for it.',
                'what_could_have_improved_outcome' => ['Propose a dated next meeting after the interest line.'],
            ],
            'sales_stage_map' => [
                [
                    'stage' => 'opening',
                    'start_seconds' => 0.0,
                    'end_seconds' => 2.5,
                    'quality' => 80,
                    'summary' => 'Polite greeting.',
                ],
                [
                    'stage' => 'discovery',
                    'start_seconds' => 2.5,
                    'end_seconds' => 6.0,
                    'quality' => 48,
                    'summary' => 'Interest appeared; needs were not explored.',
                ],
                [
                    'stage' => 'closing',
                    'start_seconds' => 6.0,
                    'end_seconds' => 9.0,
                    'quality' => 40,
                    'summary' => 'Vague follow-up.',
                ],
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    /**
     * Stored v2 JSON for presenter compatibility tests. Not valid for new analyses.
     *
     * @return array<string, mixed>
     */
    public static function legacyV2Payload(): array
    {
        $payload = self::validPayload();
        foreach (SalesAnalysisSchema::V3_REQUIRED as $key) {
            unset($payload[$key]);
        }
        unset($payload['customer_intent_confidence'], $payload['speaker_roles_confidence']);

        $payload['better_phrases'] = [[
            'original' => 'I will be in touch.',
            'suggested' => 'Can we book a 20-minute call on Thursday at 10?',
            'reason' => 'A specific next step is easier to keep.',
        ]];

        return $payload;
    }
}
