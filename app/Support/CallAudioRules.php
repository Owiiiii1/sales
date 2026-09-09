<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class CallAudioRules
{
    /**
     * @return array<int, mixed>
     */
    public static function file(): array
    {
        $maxKb = (int) config('sales-analyzer.max_audio_size_kb');
        $extensions = config('sales-analyzer.allowed_audio_extensions');
        $mimes = config('sales-analyzer.allowed_audio_mime_types');

        return [
            'required',
            File::types($extensions)->max($maxKb),
            'mimetypes:'.implode(',', $mimes),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $maxMb = (int) config('sales-analyzer.max_audio_size_mb');

        return [
            'audio.required' => 'Please choose an audio file.',
            'audio.file' => 'Please choose an audio file.',
            'audio.max' => "The audio file is too large. Maximum size is {$maxMb} MB.",
            'audio.mimes' => 'This audio format is not supported.',
            'audio.mimetypes' => 'This audio format is not supported.',
        ];
    }

    public static function validateExtension(Validator $validator, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('sales-analyzer.allowed_audio_extensions', []);

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            $validator->errors()->add('audio', 'This audio format is not supported.');
        }
    }
}
