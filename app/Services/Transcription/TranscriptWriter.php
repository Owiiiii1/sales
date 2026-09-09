<?php

namespace App\Services\Transcription;

use App\Models\Call;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\Transcription\DTO\TranscriptionResult;
use App\Services\Transcription\DTO\TranscriptionSegment;
use Illuminate\Support\Facades\DB;

class TranscriptWriter
{
    public function replace(Call $call, TranscriptionResult $result): Transcript
    {
        return DB::transaction(function () use ($call, $result) {
            $existing = Transcript::query()->where('call_id', $call->id)->first();
            $existing?->delete();

            $transcript = Transcript::query()->create([
                'call_id' => $call->id,
                'provider' => $result->provider,
                'model' => $result->model,
                'language' => $result->language,
                'raw_text' => $result->text,
                'duration_seconds' => $result->duration !== null ? (int) round($result->duration) : null,
                'confidence' => $result->confidence,
                'provider_request_id' => $result->requestId,
                'provider_metadata' => $result->metadata,
                'started_at' => $call->processing_started_at,
                'completed_at' => now(),
            ]);

            foreach (array_values($result->segments) as $index => $segment) {
                $this->storeSegment($transcript, $segment, $index);
            }

            return $transcript->load('segments');
        });
    }

    private function storeSegment(Transcript $transcript, TranscriptionSegment $segment, int $sequence): void
    {
        TranscriptSegment::query()->create([
            'transcript_id' => $transcript->id,
            'speaker' => $segment->speaker,
            'start_seconds' => $segment->start,
            'end_seconds' => $segment->end,
            'text' => $segment->text,
            'confidence' => $segment->confidence,
            'sequence' => $sequence,
        ]);
    }
}
