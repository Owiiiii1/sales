<?php

namespace App\Services\Calls;

use App\Models\Call;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CallAudioStreamer
{
    public function __construct(private CallAudioStorage $storage) {}

    public function stream(Call $call): BinaryFileResponse
    {
        $path = $this->absoluteExistingPath($call);

        $response = new BinaryFileResponse(
            $path,
            200,
            [
                'Content-Type' => $this->contentType($call),
                'X-Content-Type-Options' => 'nosniff',
                'Accept-Ranges' => 'bytes',
            ],
            false,
            ResponseHeaderBag::DISPOSITION_INLINE,
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $this->downloadName($call),
        );

        return $response;
    }

    public function download(Call $call): BinaryFileResponse
    {
        $path = $this->absoluteExistingPath($call);

        $response = new BinaryFileResponse(
            $path,
            200,
            [
                'Content-Type' => $this->contentType($call),
                'X-Content-Type-Options' => 'nosniff',
            ],
            false,
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->downloadName($call),
        );

        return $response;
    }

    private function absoluteExistingPath(Call $call): string
    {
        if (! $this->storage->exists($call->storage_path)) {
            abort(404);
        }

        return $this->storage->absolutePath($call->storage_path);
    }

    private function contentType(Call $call): string
    {
        return $call->mime_type ?: 'application/octet-stream';
    }

    private function downloadName(Call $call): string
    {
        $name = $call->original_filename ?: 'call-'.$call->id;
        $name = str_replace(["\r", "\n", '"', '/', '\\'], '_', $name);

        return $name === '' ? 'call-'.$call->id : $name;
    }
}
