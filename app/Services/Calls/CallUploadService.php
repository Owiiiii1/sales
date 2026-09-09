<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class CallUploadService
{
    public function __construct(
        private CallAudioStorage $storage,
        private AudioMetadataService $metadata,
        private CallProcessingPipeline $pipeline,
    ) {}

    /**
     * Store the audio first, then persist the Call. If the database write
     * fails, the stored file is deleted so it cannot remain orphaned.
     *
     * @param  array{company_id:int, employee_id?:int|null, recorded_at?:string|null, source?:string}  $payload
     */
    public function upload(User $user, array $payload, UploadedFile $file): Call
    {
        $path = $this->storage->store((int) $payload['company_id'], $file);

        try {
            $absolutePath = $this->storage->absolutePath($path);
            $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

            $call = Call::query()->create([
                'company_id' => $payload['company_id'],
                'employee_id' => $payload['employee_id'] ?? null,
                'source' => $payload['source'] ?? 'manual',
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'duration_seconds' => $this->metadata->durationSeconds($absolutePath, $mimeType),
                'status' => 'uploaded',
                'recorded_at' => $payload['recorded_at'] ?? null,
                'uploaded_by' => $user->id,
            ]);

            $this->pipeline->dispatch($call);

            return $call;
        } catch (Throwable $e) {
            $this->storage->deleteIfExists($path);

            Log::error('Call audio was stored but the database record could not be created.', [
                'company_id' => $payload['company_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function delete(Call $call): void
    {
        $path = $call->storage_path;

        $call->delete();

        $this->storage->deleteIfExists($path);
    }
}
