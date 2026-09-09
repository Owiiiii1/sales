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
                'original' => 'I will be in touch.',
                'suggested' => 'Can we book a 20-minute call on Thursday at 10?',
                'reason' => 'A specific next step is easier to keep.',
            ]],
            'next_step' => 'Schedule a follow-up with a clear agenda.',
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
