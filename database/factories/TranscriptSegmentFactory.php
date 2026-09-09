<?php

namespace Database\Factories;

use App\Models\Transcript;
use App\Models\TranscriptSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptSegment>
 */
class TranscriptSegmentFactory extends Factory
{
    protected $model = TranscriptSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transcript_id' => Transcript::factory(),
            'speaker' => 0,
            'start_seconds' => 0,
            'end_seconds' => 2,
            'text' => 'Hello.',
            'confidence' => 0.9,
            'sequence' => 0,
        ];
    }
}
