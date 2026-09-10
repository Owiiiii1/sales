<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\Analysis\ConversationMetricsCalculator;
use Tests\TestCase;

class ConversationMetricsCalculatorTest extends TestCase
{
    public function test_talk_percentages_and_switches_use_segments_and_roles(): void
    {
        $call = Call::factory()->create(['status' => 'transcribed']);
        $transcript = Transcript::factory()->create([
            'call_id' => $call->id,
            'duration_seconds' => 20,
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 0,
            'start_seconds' => 0,
            'end_seconds' => 12,
            'text' => 'Pitch.',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 1,
            'sequence' => 1,
            'start_seconds' => 12,
            'end_seconds' => 20,
            'text' => 'Okay.',
        ]);

        $metrics = app(ConversationMetricsCalculator::class)->for(
            $transcript->fresh('segments'),
            ['0' => 'seller', '1' => 'customer'],
        );

        $this->assertSame(60, $metrics['seller_talk_percent']);
        $this->assertSame(40, $metrics['customer_talk_percent']);
        $this->assertSame(12, $metrics['longest_seller_monologue_seconds']);
        $this->assertSame(1, $metrics['speaker_switches']);
        $this->assertSame(20, $metrics['call_duration_seconds']);
    }

    public function test_unknown_roles_leave_talk_percent_null(): void
    {
        $call = Call::factory()->create(['status' => 'transcribed']);
        $transcript = Transcript::factory()->create(['call_id' => $call->id, 'duration_seconds' => 10]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 0,
            'start_seconds' => 0,
            'end_seconds' => 10,
        ]);

        $metrics = app(ConversationMetricsCalculator::class)->for($transcript->fresh('segments'), []);

        $this->assertNull($metrics['seller_talk_percent']);
        $this->assertNull($metrics['customer_talk_percent']);
        $this->assertSame(0, $metrics['speaker_switches']);
    }

    public function test_missing_durations_return_null_percentages(): void
    {
        $call = Call::factory()->create(['status' => 'transcribed']);
        $transcript = Transcript::factory()->create(['call_id' => $call->id, 'duration_seconds' => null]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 0,
            'start_seconds' => 0,
            'end_seconds' => 0,
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 1,
            'sequence' => 1,
            'start_seconds' => 0,
            'end_seconds' => 0,
        ]);

        $metrics = app(ConversationMetricsCalculator::class)->for(
            $transcript->fresh('segments'),
            ['0' => 'seller', '1' => 'customer'],
        );

        $this->assertNull($metrics['seller_talk_percent']);
        $this->assertNull($metrics['longest_seller_monologue_seconds']);
        $this->assertSame(1, $metrics['speaker_switches']);
        $this->assertNull($metrics['call_duration_seconds']);
    }
}
