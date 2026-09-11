<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCall;
use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\Transcript;
use App\Services\Calls\StuckCallRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StuckCallRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stuck_analyzing_call_without_job_is_marked_failed(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = $this->analyzingCall(now()->subHours(2));

        $this->artisan('sales:recover-stuck-calls')
            ->expectsOutputToContain('Call #'.$call->id)
            ->assertSuccessful();

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertSame(StuckCallRecovery::SAFE_INTERRUPTION_MESSAGE, $call->error_message);
        Queue::assertNothingPushed();
    }

    public function test_queued_analyze_job_is_not_recovered(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = $this->analyzingCall(now()->subHours(2));
        $this->insertQueuedJob(AnalyzeCall::class, $call->id);

        $this->artisan('sales:recover-stuck-calls')->assertSuccessful();

        $this->assertSame('analyzing', $call->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_recent_analyzing_call_is_not_recovered_without_explicit_id(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = $this->analyzingCall(now()->subMinutes(10));

        $this->artisan('sales:recover-stuck-calls')->assertSuccessful();

        $this->assertSame('analyzing', $call->fresh()->status);
    }

    public function test_explicit_call_id_recovers_without_waiting_for_threshold(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = $this->analyzingCall(now()->subMinutes(2));

        $this->artisan('sales:recover-stuck-calls', ['--call' => $call->id])->assertSuccessful();

        $this->assertSame('failed', $call->fresh()->status);
    }

    public function test_retry_dispatches_analyze_call_for_stuck_analysis(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = $this->analyzingCall(now()->subHours(2));

        $this->artisan('sales:recover-stuck-calls', [
            '--call' => $call->id,
            '--retry' => true,
        ])->assertSuccessful();

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
        $this->assertSame('analyzing', $call->fresh()->status);
        $this->assertNull($call->fresh()->error_message);
    }

    public function test_retry_of_failed_call_with_transcript_dispatches_analyze_job(): void
    {
        $call = $this->analyzingCall(now()->subHours(2));
        $call->forceFill([
            'status' => 'failed',
            'error_message' => StuckCallRecovery::SAFE_INTERRUPTION_MESSAGE,
        ])->save();

        $this->artisan('sales:recover-stuck-calls', [
            '--call' => $call->id,
            '--retry' => true,
        ])->assertSuccessful();

        Queue::assertPushed(AnalyzeCall::class, fn (AnalyzeCall $job): bool => $job->callId === $call->id);
    }

    public function test_retry_dispatches_transcribe_call_for_stuck_processing(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = Call::factory()->create([
            'status' => 'processing',
            'company_id' => null,
        ]);
        Call::query()->whereKey($call->id)->update([
            'updated_at' => now()->subHours(2),
            'processing_started_at' => now()->subHours(2),
        ]);

        $this->artisan('sales:recover-stuck-calls', ['--retry' => true])->assertSuccessful();

        Queue::assertPushed(TranscribeCall::class, fn (TranscribeCall $job): bool => $job->callId === $call->id);
        $this->assertSame('processing', $call->fresh()->status);
    }

    public function test_completed_call_is_not_recovered(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $call = Call::factory()->create([
            'status' => 'completed',
            'company_id' => null,
        ]);
        Call::query()->whereKey($call->id)->update([
            'updated_at' => now()->subHours(3),
        ]);

        $this->artisan('sales:recover-stuck-calls', ['--call' => $call->id])->assertSuccessful();

        $this->assertSame('completed', $call->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_sync_without_retry_is_rejected(): void
    {
        $this->artisan('sales:recover-stuck-calls', ['--sync' => true])
            ->assertFailed();
    }

    private function analyzingCall(Carbon $stuckAt): Call
    {
        $call = Call::factory()->create([
            'status' => 'analyzing',
            'company_id' => null,
            'error_message' => null,
        ]);
        Transcript::factory()->create([
            'call_id' => $call->id,
            'raw_text' => 'Hello, this is a sales call transcript.',
        ]);
        Call::query()->whereKey($call->id)->update([
            'updated_at' => $stuckAt,
            'processing_started_at' => $stuckAt,
        ]);

        return $call->fresh(['transcript']);
    }

    private function insertQueuedJob(string $jobClass, int $callId): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => $jobClass,
                'data' => [
                    'commandName' => $jobClass,
                    'command' => serialize(new $jobClass($callId)),
                ],
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);
    }
}
