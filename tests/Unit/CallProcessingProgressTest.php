<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\Transcript;
use App\Support\CallProcessingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallProcessingProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_and_processing_map_to_transcription_active(): void
    {
        foreach (['uploaded', 'processing'] as $status) {
            $call = Call::factory()->create(['status' => $status]);
            $states = CallProcessingProgress::states($call);

            $this->assertSame('completed', $states['uploaded'], $status);
            $this->assertSame('active', $states['transcription'], $status);
            $this->assertSame('pending', $states['preparing'], $status);
            $this->assertSame('pending', $states['analysis'], $status);
            $this->assertSame('pending', $states['complete'], $status);
            $this->assertTrue(CallProcessingProgress::for($call)['cancellable']);
        }
    }

    public function test_transcribed_maps_to_preparing_analysis(): void
    {
        $call = Call::factory()->create(['status' => 'transcribed']);
        Transcript::factory()->create(['call_id' => $call->id]);
        $states = CallProcessingProgress::states($call->fresh('transcript'));

        $this->assertSame('completed', $states['transcription']);
        $this->assertSame('active', $states['preparing']);
        $this->assertSame('pending', $states['analysis']);
    }

    public function test_analyzing_maps_to_analysis_active(): void
    {
        $call = Call::factory()->create(['status' => 'analyzing']);
        $states = CallProcessingProgress::states($call);

        $this->assertSame('completed', $states['preparing']);
        $this->assertSame('active', $states['analysis']);
        $this->assertSame('pending', $states['complete']);
    }

    public function test_completed_maps_all_steps_complete(): void
    {
        $call = Call::factory()->create(['status' => 'completed']);
        $states = CallProcessingProgress::states($call);

        $this->assertSame(['uploaded', 'transcription', 'preparing', 'analysis', 'complete'], array_keys($states));
        $this->assertSame(['completed', 'completed', 'completed', 'completed', 'completed'], array_values($states));
        $this->assertTrue(CallProcessingProgress::for($call)['compact']);
        $this->assertFalse(CallProcessingProgress::for($call)['cancellable']);
    }

    public function test_analysis_pending_maps_to_unavailable_analysis(): void
    {
        $call = Call::factory()->create(['status' => 'analysis_pending']);
        Transcript::factory()->create(['call_id' => $call->id]);
        $progress = CallProcessingProgress::for($call->fresh('transcript'));

        $this->assertSame('unavailable', $progress['steps'][3]['state']);
        $this->assertSame('unavailable', $progress['headline']);
        $this->assertFalse($progress['cancellable']);
    }

    public function test_failed_transcription_marks_transcription_failed(): void
    {
        $call = Call::factory()->create(['status' => 'failed']);
        $states = CallProcessingProgress::states($call);

        $this->assertSame('failed', $states['transcription']);
        $this->assertSame('pending', $states['analysis']);
    }

    public function test_failed_analysis_marks_analysis_failed(): void
    {
        $call = Call::factory()->create(['status' => 'failed']);
        Transcript::factory()->create(['call_id' => $call->id]);
        $states = CallProcessingProgress::states($call->fresh('transcript'));

        $this->assertSame('completed', $states['transcription']);
        $this->assertSame('failed', $states['analysis']);
        $this->assertSame('pending', $states['complete']);
    }

    public function test_cancelled_uses_cancelled_stage(): void
    {
        $duringTranscription = Call::factory()->create([
            'status' => 'cancelled',
            'cancelled_stage' => 'transcription',
        ]);
        $this->assertSame('cancelled', CallProcessingProgress::states($duringTranscription)['transcription']);

        $duringPreparing = Call::factory()->create([
            'status' => 'cancelled',
            'cancelled_stage' => 'preparing',
        ]);
        $this->assertSame('cancelled', CallProcessingProgress::states($duringPreparing)['preparing']);

        $duringAnalysis = Call::factory()->create([
            'status' => 'cancelled',
            'cancelled_stage' => 'analysis',
        ]);
        $this->assertSame('cancelled', CallProcessingProgress::states($duringAnalysis)['analysis']);
        $this->assertSame('stopped_analysis', CallProcessingProgress::for($duringAnalysis)['headline']);
    }
}
