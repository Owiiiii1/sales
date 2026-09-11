<?php

namespace App\Support;

use App\Models\Call;

class CallProcessingProgress
{
    /**
     * @return array{
     *     cancellable: bool,
     *     transcript_available: bool,
     *     compact: bool,
     *     headline: string,
     *     steps: array<int, array{key: string, state: string}>
     * }
     */
    public static function for(Call $call): array
    {
        $states = self::states($call);

        return [
            'cancellable' => $call->isCancellable(),
            'transcript_available' => $call->transcript !== null,
            'compact' => in_array($call->status, ['completed', 'cancelled'], true),
            'headline' => self::headline($call),
            'steps' => [
                ['key' => 'uploaded', 'state' => $states['uploaded']],
                ['key' => 'transcription', 'state' => $states['transcription']],
                ['key' => 'preparing', 'state' => $states['preparing']],
                ['key' => 'analysis', 'state' => $states['analysis']],
                ['key' => 'complete', 'state' => $states['complete']],
            ],
        ];
    }

    /**
     * @return array{uploaded: string, transcription: string, preparing: string, analysis: string, complete: string}
     */
    public static function states(Call $call): array
    {
        return match ($call->status) {
            'uploaded', 'processing' => [
                'uploaded' => 'completed',
                'transcription' => 'active',
                'preparing' => 'pending',
                'analysis' => 'pending',
                'complete' => 'pending',
            ],
            'transcribed' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'active',
                'analysis' => 'pending',
                'complete' => 'pending',
            ],
            'analyzing' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'completed',
                'analysis' => 'active',
                'complete' => 'pending',
            ],
            'completed' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'completed',
                'analysis' => 'completed',
                'complete' => 'completed',
            ],
            'analysis_pending' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'completed',
                'analysis' => 'unavailable',
                'complete' => 'pending',
            ],
            'failed' => self::failedStates($call),
            'cancelled' => self::cancelledStates($call),
            default => [
                'uploaded' => 'pending',
                'transcription' => 'pending',
                'preparing' => 'pending',
                'analysis' => 'pending',
                'complete' => 'pending',
            ],
        };
    }

    /**
     * @return array{uploaded: string, transcription: string, preparing: string, analysis: string, complete: string}
     */
    private static function failedStates(Call $call): array
    {
        if ($call->transcript === null) {
            return [
                'uploaded' => 'completed',
                'transcription' => 'failed',
                'preparing' => 'pending',
                'analysis' => 'pending',
                'complete' => 'pending',
            ];
        }

        return [
            'uploaded' => 'completed',
            'transcription' => 'completed',
            'preparing' => 'completed',
            'analysis' => 'failed',
            'complete' => 'pending',
        ];
    }

    /**
     * @return array{uploaded: string, transcription: string, preparing: string, analysis: string, complete: string}
     */
    private static function cancelledStates(Call $call): array
    {
        return match ($call->cancelled_stage) {
            'analysis' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'completed',
                'analysis' => 'cancelled',
                'complete' => 'pending',
            ],
            'preparing' => [
                'uploaded' => 'completed',
                'transcription' => 'completed',
                'preparing' => 'cancelled',
                'analysis' => 'pending',
                'complete' => 'pending',
            ],
            default => [
                'uploaded' => 'completed',
                'transcription' => 'cancelled',
                'preparing' => 'pending',
                'analysis' => 'pending',
                'complete' => 'pending',
            ],
        };
    }

    private static function headline(Call $call): string
    {
        return match ($call->status) {
            'completed' => 'complete',
            'cancelled' => $call->cancelled_stage === 'analysis' ? 'stopped_analysis' : 'stopped',
            'failed' => 'failed',
            'analysis_pending' => 'unavailable',
            default => 'processing',
        };
    }
}
