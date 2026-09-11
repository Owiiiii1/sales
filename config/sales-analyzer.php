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

    'public_upload_per_minute' => (int) env('SALES_PUBLIC_UPLOAD_PER_MINUTE', 10),

    'public_status_per_minute' => (int) env('SALES_PUBLIC_STATUS_PER_MINUTE', 60),

    'transcription' => [
        'provider' => env('SALES_TRANSCRIPTION_PROVIDER', 'elevenlabs'),
        'model' => env('ELEVENLABS_STT_MODEL', 'scribe_v2'),
        'api_key' => env('ELEVENLABS_API_KEY'),
        'endpoint' => env('ELEVENLABS_STT_ENDPOINT', 'https://api.elevenlabs.io/v1/speech-to-text'),
        'user_endpoint' => env('ELEVENLABS_USER_ENDPOINT', 'https://api.elevenlabs.io/v1/user'),
        'timeout' => (int) env('ELEVENLABS_STT_TIMEOUT', 120),
        'supported_languages' => ['en', 'ru', 'uk'],
        'supported_models' => [
            ['id' => 'scribe_v2', 'name' => 'Scribe v2'],
        ],
        'diarization' => true,
        'timestamps' => true,
    ],

    'analysis' => [
        'schema_version' => 3,
        // Character budget for company knowledge packed into the LLM prompt.
        'context_budget_characters' => (int) env('SALES_ANALYSIS_CONTEXT_BUDGET', 50000),
        'max_output_tokens' => [
            'default' => 16384,
            'min' => 4096,
            'max' => 32768,
            'providers' => [
                'openai' => 32768,
                'anthropic' => 16384,
                'gemini' => 16384,
            ],
        ],
    ],

    'analytics' => [
        'default_period' => 'last_30',
        'score_good' => 80,
        'score_warning' => 60,
        'recent_calls' => 20,
        'latest_violations' => 10,
        'recent_findings' => 8,
        'top_missed_questions' => 10,
    ],

];
