<?php

namespace App\Services\Calls;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CallAudioStorage
{
    public function diskName(): string
    {
        return (string) config('sales-analyzer.storage_disk', 'calls');
    }

    public function store(?int $companyId, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('sales-analyzer.allowed_audio_extensions', []);

        if (! in_array($extension, $allowed, true)) {
            $extension = 'bin';
        }

        $prefix = $companyId !== null ? (string) $companyId : 'public';
        $directory = $prefix.'/'.now()->format('Y').'/'.now()->format('m');
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $file->storeAs($directory, $filename, [
            'disk' => $this->diskName(),
            'visibility' => 'private',
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Call audio could not be stored.');
        }

        $this->relaxDirectoryPermissions($path);

        return $path;
    }

    /**
     * PHP-FPM often creates directories with umask 0077. Relax them so the
     * deploy user can manage files on the private disk without 777.
     */
    private function relaxDirectoryPermissions(string $path): void
    {
        $root = rtrim($this->absolutePath(''), DIRECTORY_SEPARATOR);
        $directory = dirname($this->absolutePath($path));

        while ($directory !== $root && str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) {
            @chmod($directory, 0775);
            $directory = dirname($directory);
        }
    }

    public function exists(?string $path): bool
    {
        return filled($path) && Storage::disk($this->diskName())->exists($path);
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk($this->diskName())->path($path);
    }

    public function deleteIfExists(?string $path): void
    {
        if (! $this->exists($path)) {
            return;
        }

        Storage::disk($this->diskName())->delete($path);
        $this->pruneEmptyDirectories($path);
    }

    private function pruneEmptyDirectories(string $path): void
    {
        $disk = Storage::disk($this->diskName());
        $parts = explode('/', $path);
        array_pop($parts);

        while ($parts !== []) {
            $directory = implode('/', $parts);
            if ($disk->files($directory) !== [] || $disk->directories($directory) !== []) {
                break;
            }

            $disk->deleteDirectory($directory);
            array_pop($parts);
        }
    }
}
