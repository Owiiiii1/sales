<?php

namespace App\Jobs;

use App\Exceptions\Transcription\PermanentTranscriptionException;
use App\Exceptions\Transcription\TransientTranscriptionException;
use App\Models\Call;
use App\Services\Calls\CallAudioStorage;
use App\Services\Transcription\TranscriptionProvider;
use App\Services\Transcription\TranscriptWriter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranscribeCall implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 600];

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $callId) {}

    public function uniqueId(): string
    {
        return (string) $this->callId;
    }

    public function handle(
        TranscriptionProvider $provider,
        TranscriptWriter $writer,
        CallAudioStorage $storage,
    ): void {
        $call = Call::query()->find($this->callId);

        if ($call === null || $call->isCancelled()) {
            return;
        }

        if (! $storage->exists($call->storage_path)) {
            $this->markFailed($call, new PermanentTranscriptionException('Call audio file is missing.'));

            return;
        }

        Call::query()
            ->whereKey($call->id)
            ->where('status', '!=', Call::STATUS_CANCELLED)
            ->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'error_message' => null,
            ]);

        $call->refresh();

        if ($call->isCancelled()) {
            return;
        }

        try {
            $result = $provider->transcribe($call);

            $shouldDispatch = DB::transaction(function () use ($writer, $result): bool {
                $call = Call::query()->whereKey($this->callId)->lockForUpdate()->first();

                if ($call === null) {
                    return false;
                }

                $transcript = $writer->replace($call, $result);
                $duration = $transcript->duration_seconds ?? $call->duration_seconds;

                if ($call->isCancelled()) {
                    return false;
                }

                $call->forceFill([
                    'status' => 'transcribed',
                    'duration_seconds' => $duration,
                    'processing_completed_at' => now(),
                    'error_message' => null,
                ])->save();

                return true;
            });

            if ($shouldDispatch) {
                $fresh = Call::query()->find($this->callId);
                if ($fresh !== null && ! $fresh->isCancelled()) {
                    AnalyzeCall::dispatch($fresh->id);
                }
            }
        } catch (PermanentTranscriptionException $e) {
            $this->markFailed($call->fresh() ?? $call, $e);
        } catch (TransientTranscriptionException $e) {
            try {
                Log::warning('Transient transcription failure; job will retry if attempts remain.', [
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

        if ($call === null || in_array($call->status, ['transcribed', 'analysis_pending', 'analyzing', 'completed', Call::STATUS_CANCELLED], true)) {
            return;
        }

        $wrapped = $exception instanceof PermanentTranscriptionException
            ? $exception
            : new PermanentTranscriptionException($exception?->getMessage() ?: 'Transcription job failed.');

        $this->markFailed($call, $wrapped);
    }

    private function markFailed(Call $call, PermanentTranscriptionException $exception): void
    {
        if ($call->isCancelled()) {
            return;
        }

        Call::query()
            ->whereKey($call->id)
            ->whereNotIn('status', [
                Call::STATUS_CANCELLED,
                'transcribed',
                'analysis_pending',
                'analyzing',
                'completed',
            ])
            ->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'error_message' => $exception->publicMessage(),
            ]);

        try {
            Log::error('Call transcription failed.', [
                'call_id' => $call->id,
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Logging must not leave the call stuck in processing.
        }
    }
}
