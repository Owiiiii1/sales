<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\Analysis\ConversationMetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_discovery_talk_balance_uses_stage_map(): void
    {
        $transcript = $this->timedTranscript();
        $calculator = app(ConversationMetricsCalculator::class);
        $stageMap = [
            ['stage' => 'opening', 'start_seconds' => 0, 'end_seconds' => 10],
            ['stage' => 'discovery', 'start_seconds' => 10, 'end_seconds' => 40],
        ];

        $metrics = $calculator->byStage(
            $transcript,
            ['0' => 'seller', '1' => 'customer'],
            $stageMap,
        );
        $balance = $calculator->discoveryTalkBalance($metrics);

        $this->assertSame(33, $balance['seller_talk_percent']);
        $this->assertSame(67, $balance['customer_talk_percent']);
    }

    public function test_missing_stage_map_returns_null_discovery_balance(): void
    {
        $transcript = $this->timedTranscript();
        $calculator = app(ConversationMetricsCalculator::class);

        $this->assertSame([], $calculator->byStage($transcript, ['0' => 'seller', '1' => 'customer'], []));
        $this->assertNull($calculator->discoveryTalkBalance([]));
    }

    public function test_invalid_stage_timestamps_are_safe(): void
    {
        $transcript = $this->timedTranscript();
        $calculator = app(ConversationMetricsCalculator::class);
        $metrics = $calculator->byStage(
            $transcript,
            ['0' => 'seller', '1' => 'customer'],
            [
                ['stage' => 'discovery', 'start_seconds' => 40, 'end_seconds' => 10],
                ['stage' => 'discovery', 'start_seconds' => -5, 'end_seconds' => 20],
                ['stage' => 'discovery', 'start_seconds' => null, 'end_seconds' => 20],
            ],
        );

        $this->assertSame([], $metrics);
        $this->assertNull($calculator->discoveryTalkBalance($metrics));
    }

    private function timedTranscript(): Transcript
    {
        $call = Call::factory()->create(['status' => 'transcribed']);
        $transcript = Transcript::factory()->create([
            'call_id' => $call->id,
            'duration_seconds' => 40,
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 0,
            'start_seconds' => 0,
            'end_seconds' => 10,
            'text' => 'Hello.',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 0,
            'sequence' => 1,
            'start_seconds' => 10,
            'end_seconds' => 20,
            'text' => 'What do you need?',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'speaker' => 1,
            'sequence' => 2,
            'start_seconds' => 20,
            'end_seconds' => 40,
            'text' => 'A package for Kyiv.',
        ]);

        return $transcript->fresh('segments');
    }
}
