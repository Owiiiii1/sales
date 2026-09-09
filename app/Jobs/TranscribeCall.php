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

        if ($call === null) {
            return;
        }

        if (! $storage->exists($call->storage_path)) {
            $this->markFailed($call, new PermanentTranscriptionException('Call audio file is missing.'));

            return;
        }

        $call->forceFill([
            'status' => 'processing',
            'processing_started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $result = $provider->transcribe($call);
            $transcript = $writer->replace($call, $result);

            $duration = $transcript->duration_seconds ?? $call->duration_seconds;

            $call->forceFill([
                'status' => 'transcribed',
                'duration_seconds' => $duration,
                'processing_completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (PermanentTranscriptionException $e) {
            $this->markFailed($call, $e);
        } catch (TransientTranscriptionException $e) {
            Log::warning('Transient transcription failure; job will retry if attempts remain.', [
                'call_id' => $call->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $call = Call::query()->find($this->callId);

        if ($call === null || $call->status === 'transcribed') {
            return;
        }

        $wrapped = $exception instanceof PermanentTranscriptionException
            ? $exception
            : new PermanentTranscriptionException($exception?->getMessage() ?: 'Transcription job failed.');

        $this->markFailed($call, $wrapped);
    }

    private function markFailed(Call $call, PermanentTranscriptionException $exception): void
    {
        Log::error('Call transcription failed.', [
            'call_id' => $call->id,
            'error' => $exception->getMessage(),
        ]);

        $call->forceFill([
            'status' => 'failed',
            'processing_completed_at' => now(),
            'error_message' => $exception->publicMessage(),
        ])->save();
    }
}
