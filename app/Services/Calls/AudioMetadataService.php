<?php

namespace App\Services\Calls;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AudioMetadataService
{
    /**
     * Best-effort duration in whole seconds. Returns null when it cannot be
     * determined without extra system packages.
     */
    public function durationSeconds(string $absolutePath, ?string $mimeType = null): ?int
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return null;
        }

        $fromProbe = $this->durationFromFfprobe($absolutePath);
        if ($fromProbe !== null) {
            return $fromProbe;
        }

        return $this->durationFromWavHeader($absolutePath);
    }

    public function ffprobeBinary(): ?string
    {
        foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function durationFromFfprobe(string $absolutePath): ?int
    {
        $binary = $this->ffprobeBinary();
        if ($binary === null) {
            return null;
        }

        $process = new Process([
            $binary,
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $absolutePath,
        ]);
        $process->setTimeout(10);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('ffprobe failed to read audio duration.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $raw = trim($process->getOutput());
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $seconds = (int) round((float) $raw);

        return $seconds >= 0 ? $seconds : null;
    }

    private function durationFromWavHeader(string $absolutePath): ?int
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $header = fread($handle, 12);
            if ($header === false || strlen($header) < 12) {
                return null;
            }

            if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
                return null;
            }

            $byteRate = null;
            $dataSize = null;

            while (! feof($handle)) {
                $chunkHeader = fread($handle, 8);
                if ($chunkHeader === false || strlen($chunkHeader) < 8) {
                    break;
                }

                $chunkId = substr($chunkHeader, 0, 4);
                $chunkSize = unpack('V', substr($chunkHeader, 4, 4))[1] ?? 0;

                if ($chunkId === 'fmt ') {
                    $fmt = fread($handle, $chunkSize);
                    if ($fmt === false || strlen($fmt) < 16) {
                        return null;
                    }
                    $byteRate = unpack('V', substr($fmt, 8, 4))[1] ?? null;
                    continue;
                }

                if ($chunkId === 'data') {
                    $dataSize = $chunkSize;
                    break;
                }

                if ($chunkSize > 0) {
                    fseek($handle, $chunkSize, SEEK_CUR);
                }
            }
        } finally {
            fclose($handle);
        }

        if (! is_int($byteRate) || $byteRate <= 0 || ! is_int($dataSize) || $dataSize < 0) {
            return null;
        }

        return (int) floor($dataSize / $byteRate);
    }
}
