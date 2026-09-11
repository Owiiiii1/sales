<?php

namespace App\Services\Calls;

use App\Jobs\AnalyzeCall;
use App\Jobs\TranscribeCall;
use App\Models\Call;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class StuckCallRecovery
{
    public const SAFE_INTERRUPTION_MESSAGE = 'Processing interrupted or worker job was lost';

    /**
     * @return list<array{id:int, status:string, action:string}>
     */
    public function recover(?int $callId, int $minutes, bool $retry, bool $sync = false): array
    {
        $minutes = max(5, $minutes);
        $cutoff = now()->subMinutes($minutes);
        $actions = [];

        foreach ($this->candidates($callId, $cutoff, $retry) as $call) {
            $actions[] = $this->recoverOne($call, $retry, $sync);
        }

        return $actions;
    }

    /**
     * @return Collection<int, Call>
     */
    private function candidates(?int $callId, Carbon $cutoff, bool $retry): Collection
    {
        $query = Call::query()->with('transcript');

        if ($callId !== null) {
            $query->whereKey($callId);

            if ($retry) {
                $query->whereIn('status', ['processing', 'analyzing', 'transcribed', 'failed']);
            } else {
                $query->whereIn('status', ['processing', 'analyzing']);
            }

            return $query->orderBy('id')->get();
        }

        return $query
            ->whereIn('status', ['processing', 'analyzing'])
            ->where(function ($inner) use ($cutoff): void {
                $inner->where('updated_at', '<=', $cutoff)
                    ->orWhere('processing_started_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{id:int, status:string, action:string}
     */
    private function recoverOne(Call $call, bool $retry, bool $sync): array
    {
        if ($this->hasQueuedJob($call)) {
            return [
                'id' => $call->id,
                'status' => $call->status,
                'action' => 'skipped_queued',
            ];
        }

        if ($retry) {
            $job = $this->retryJob($call);

            if ($job === null) {
                $this->markFailed($call);

                return [
                    'id' => $call->id,
                    'status' => 'failed',
                    'action' => 'failed_unretryable',
                ];
            }

            if ($sync) {
                Bus::dispatchSync($job);
            } else {
                Bus::dispatch($job);
            }

            return [
                'id' => $call->id,
                'status' => $call->fresh()?->status ?? $call->status,
                'action' => $sync ? 'retried_sync' : 'retried',
            ];
        }

        $this->markFailed($call);

        return [
            'id' => $call->id,
            'status' => 'failed',
            'action' => 'failed',
        ];
    }

    private function retryJob(Call $call): AnalyzeCall|TranscribeCall|null
    {
        if ($call->status === 'processing') {
            return new TranscribeCall($call->id);
        }

        if (
            in_array($call->status, ['analyzing', 'transcribed', 'failed'], true)
            && $call->transcript !== null
            && filled($call->transcript->raw_text)
        ) {
            return new AnalyzeCall($call->id);
        }

        return null;
    }

    private function markFailed(Call $call): void
    {
        Call::query()
            ->whereKey($call->id)
            ->whereIn('status', ['processing', 'analyzing'])
            ->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'error_message' => self::SAFE_INTERRUPTION_MESSAGE,
            ]);
    }

    private function hasQueuedJob(Call $call): bool
    {
        $jobClass = $call->status === 'processing'
            ? TranscribeCall::class
            : AnalyzeCall::class;

        foreach (DB::table('jobs')->select('payload')->cursor() as $row) {
            if ($this->payloadTargetsCall((string) $row->payload, $jobClass, $call->id)) {
                return true;
            }
        }

        return false;
    }

    private function payloadTargetsCall(string $payload, string $jobClass, int $callId): bool
    {
        $decoded = json_decode($payload, true);
        $command = is_array($decoded)
            ? (string) data_get($decoded, 'data.command', '')
            : '';
        $haystack = $payload."\n".$command."\n".(string) data_get($decoded, 'displayName', '');

        if (! str_contains($haystack, class_basename($jobClass)) && ! str_contains($haystack, $jobClass)) {
            return false;
        }

        return str_contains($haystack, 'callId";i:'.$callId.';')
            || str_contains($haystack, '"callId":'.$callId);
    }
}
