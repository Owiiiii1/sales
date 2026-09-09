<?php

namespace App\Support;

use App\Models\Call;
use App\Models\TranscriptSegment;

class TranscriptPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function public(Call $call): ?array
    {
        $transcript = $call->transcript;

        if ($transcript === null) {
            return null;
        }

        $visible = in_array($call->status, ['transcribed', 'analysis_pending', 'analyzing', 'completed', 'failed'], true);

        if (! $visible) {
            return null;
        }

        return [
            'language' => $transcript->language,
            'duration_seconds' => $transcript->duration_seconds ?? $call->duration_seconds,
            'text' => $transcript->raw_text,
            'segments' => $transcript->segments->map(fn (TranscriptSegment $segment): array => self::segment($segment))->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function admin(Call $call): ?array
    {
        $transcript = $call->transcript;

        if ($transcript === null) {
            return null;
        }

        return [
            'provider' => $transcript->provider,
            'provider_label' => self::providerLabel($transcript->provider),
            'model' => $transcript->model,
            'model_label' => self::modelLabel($transcript->model),
            'language' => $transcript->language,
            'duration_seconds' => $transcript->duration_seconds ?? $call->duration_seconds,
            'text' => $transcript->raw_text,
            'segments' => $transcript->segments->map(fn (TranscriptSegment $segment): array => self::segment($segment))->all(),
        ];
    }

    /**
     * @return array{speaker:int, speaker_label:string, start_seconds:float, end_seconds:float, start_label:string, text:string}
     */
    public static function segment(TranscriptSegment $segment): array
    {
        return [
            'speaker' => $segment->speaker,
            'speaker_label' => $segment->speakerLabel(),
            'start_seconds' => $segment->start_seconds,
            'end_seconds' => $segment->end_seconds,
            'start_label' => self::timestamp($segment->start_seconds),
            'text' => $segment->text,
        ];
    }

    public static function timestamp(float $seconds): string
    {
        $total = (int) floor(max(0, $seconds));
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $secs = $total % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    public static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'elevenlabs' => 'ElevenLabs',
            default => $provider,
        };
    }

    public static function modelLabel(string $model): string
    {
        return match ($model) {
            'scribe_v2' => 'Scribe v2',
            default => $model,
        };
    }
}
