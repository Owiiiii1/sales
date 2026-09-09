<?php

namespace App\Services\Calls;

use App\Models\Call;

/**
 * Architectural hook for Phase 3 transcription / analysis.
 *
 * Phase 2 does not dispatch this pipeline after upload (DEC-015).
 * A successful upload stays `uploaded` and must not look like processing.
 */
class CallProcessingPipeline
{
    public function shouldDispatchAfterUpload(): bool
    {
        return false;
    }

    public function dispatch(Call $call): void
    {
        if (! $this->shouldDispatchAfterUpload()) {
            return;
        }
    }
}
