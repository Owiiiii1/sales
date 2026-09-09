<?php

$maxAudioSizeMb = max(1, (int) env('SALES_AUDIO_MAX_MB', 200));

return [

    /*
    |--------------------------------------------------------------------------
    | Call audio storage
    |--------------------------------------------------------------------------
    |
    | Audio is stored on a private disk. It is never published via /storage.
    |
    */

    'storage_disk' => env('SALES_AUDIO_DISK', 'calls'),

    'max_audio_size_mb' => $maxAudioSizeMb,

    'max_audio_size_kb' => $maxAudioSizeMb * 1024,

    'allowed_audio_extensions' => [
        'mp3',
        'wav',
        'm4a',
        'mp4',
        'ogg',
        'webm',
    ],

    'allowed_audio_mime_types' => [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/vnd.wave',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/aac',
        'audio/ogg',
        'audio/vorbis',
        'audio/webm',
        'video/webm',
        'video/mp4',
        'application/ogg',
    ],

];
