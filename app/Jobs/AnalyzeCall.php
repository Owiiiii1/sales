<?php

namespace App\Jobs;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use App\Models\Call;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\SalesAnalysisProvider;
use App\Services\Analysis\SalesAnalysisWriter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCall implements ShouldBeUniqueUntilProcessing, ShouldQueue
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

    public function handle(SalesAnalysisProvider $provider, SalesAnalysisWriter $writer): void
    {
        $call = Call::query()->with(['transcript.segments', 'company'])->find($this->callId);

        if ($call === null) {
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
            $call->forceFill([
                'status' => 'analysis_pending',
                'error_message' => null,
            ])->save();

            return;
        }

        $startedAt = now();
        $call->forceFill([
            'status' => 'analyzing',
            'error_message' => null,
        ])->save();

        try {
            $result = $provider->analyze($transcript, new AnalysisContext(
                language: $transcript->language,
                companyName: $call->company?->name,
            ));

            $writer->replace($call, $result, $startedAt);

            $call->forceFill([
                'status' => 'completed',
                'processing_completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (PermanentAnalysisException $e) {
            $this->markFailed($call, $e);
        } catch (TransientAnalysisException $e) {
            Log::warning('Transient sales analysis failure; job will retry if attempts remain.', [
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

        if ($call === null || $call->status === 'completed') {
            return;
        }

        $wrapped = $exception instanceof PermanentAnalysisException
            ? $exception
            : new PermanentAnalysisException($exception?->getMessage() ?: 'Analysis job failed.');

        $this->markFailed($call, $wrapped);
    }

    private function markFailed(Call $call, PermanentAnalysisException $exception): void
    {
        Log::error('Call sales analysis failed.', [
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
