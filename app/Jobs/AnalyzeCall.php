<?php

namespace App\Jobs;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use App\Models\Call;
use App\Services\Analysis\AnalysisContextBuilder;
use App\Services\Analysis\ConversationMetricsCalculator;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisWriter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCall implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 600];

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public int $callId) {}

    public function uniqueId(): string
    {
        return (string) $this->callId;
    }

    public function handle(
        SalesAnalysisProvider $provider,
        SalesAnalysisWriter $writer,
        AnalysisContextBuilder $contexts,
    ): void {
        $call = Call::query()->with(['transcript.segments', 'company'])->find($this->callId);

        if ($call === null || $call->isCancelled()) {
            return;
        }

        $transcript = $call->transcript;

        if ($transcript === null || ! filled($transcript->raw_text)) {
            $this->markFailed($call, new PermanentAnalysisException(
                'Call has no transcript to analyze.',
                'Analysis is not available yet.',
            ));

            return;
        }

        if (! $provider->isConfigured()) {
            Call::query()
                ->whereKey($call->id)
                ->where('status', '!=', Call::STATUS_CANCELLED)
                ->update([
                    'status' => 'analysis_pending',
                    'error_message' => null,
                ]);

            return;
        }

        $startedAt = now();
        Call::query()
            ->whereKey($call->id)
            ->where('status', '!=', Call::STATUS_CANCELLED)
            ->update([
                'status' => 'analyzing',
                'error_message' => null,
            ]);

        $call->refresh()->load(['transcript.segments', 'company']);

        if ($call->isCancelled()) {
            return;
        }

        try {
            $result = $provider->analyze($transcript, $contexts->build($call));
            $result = app(ConversationMetricsCalculator::class)->attach($transcript, $result);

            DB::transaction(function () use ($writer, $result, $startedAt): void {
                $call = Call::query()->whereKey($this->callId)->lockForUpdate()->first();

                if ($call === null || $call->isCancelled()) {
                    return;
                }

                $writer->replace($call, $result, $startedAt);

                $call->forceFill([
                    'status' => 'completed',
                    'processing_completed_at' => now(),
                    'error_message' => null,
                ])->save();
            });
        } catch (PermanentAnalysisException $e) {
            $this->markFailed($call->fresh() ?? $call, $e);
        } catch (TransientAnalysisException $e) {
            try {
                Log::warning('Transient sales analysis failure; job will retry if attempts remain.', [
                    'call_id' => $this->callId,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Logging must not convert a retryable failure into a stuck call.
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $call = Call::query()->find($this->callId);

        if ($call === null || in_array($call->status, ['completed', Call::STATUS_CANCELLED], true)) {
            return;
        }

        $wrapped = $exception instanceof PermanentAnalysisException
            ? $exception
            : new PermanentAnalysisException($exception?->getMessage() ?: 'Analysis job failed.');

        $this->markFailed($call, $wrapped);
    }

    private function markFailed(Call $call, PermanentAnalysisException $exception): void
    {
        if ($call->isCancelled()) {
            return;
        }

        Call::query()
            ->whereKey($call->id)
            ->whereNotIn('status', [Call::STATUS_CANCELLED, 'completed'])
            ->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'error_message' => $exception->publicMessage(),
            ]);

        try {
            Log::error('Call sales analysis failed.', [
                'call_id' => $call->id,
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Logging must not leave the call stuck in analyzing.
        }
    }
}
