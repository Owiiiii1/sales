<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\Transcript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcript>
 */
class TranscriptFactory extends Factory
{
    protected $model = Transcript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'call_id' => Call::factory()->state(['status' => 'transcribed']),
            'provider' => 'elevenlabs',
            'model' => 'scribe_v2',
            'language' => 'en',
            'raw_text' => 'Hello, thanks for taking the time. Hi, I am interested in your offer.',
            'duration_seconds' => 12,
            'confidence' => 0.95,
            'provider_request_id' => 'req_test',
            'provider_metadata' => [
                'request_id' => 'req_test',
                'detected_language' => 'en',
                'model' => 'scribe_v2',
                'speaker_count' => 2,
            ],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}
