<?php

namespace App\Services\Calls;

use App\Models\Call;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CallCancellationService
{
    public function cancelByPublicToken(string $publicToken): Call
    {
        return $this->cancelLocked(
            fn () => Call::query()->where('public_token', $publicToken)->lockForUpdate()->first()
        );
    }

    public function cancel(Call $call): Call
    {
        return $this->cancelLocked(
            fn () => Call::query()->whereKey($call->id)->lockForUpdate()->first()
        );
    }

    /**
     * @param  callable(): (?Call)  $lock
     */
    private function cancelLocked(callable $lock): Call
    {
        return DB::transaction(function () use ($lock): Call {
            $call = $lock();

            if ($call === null) {
                throw new NotFoundHttpException();
            }

            if ($call->isCancelled()) {
                return $call;
            }

            if (! $call->isCancellable()) {
                throw new ConflictHttpException(__('This call can no longer be stopped.'));
            }

            $call->forceFill([
                'status' => Call::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_stage' => Call::cancelStageForStatus((string) $call->status),
                'processing_completed_at' => now(),
            ])->save();

            return $call;
        });
    }
}
