<?php

namespace App\Services\Calls;

use App\Jobs\TranscribeCall;
use App\Models\Call;

class CallProcessingPipeline
{
    public function shouldDispatchAfterUpload(): bool
    {
        return true;
    }

    public function dispatch(Call $call): void
    {
        if (! $this->shouldDispatchAfterUpload() || $call->isCancelled()) {
            return;
        }

        TranscribeCall::dispatch($call->id);
    }
}
