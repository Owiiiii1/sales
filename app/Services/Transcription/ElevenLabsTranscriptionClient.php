<?php

namespace App\Services\Transcription;

use App\Exceptions\Transcription\PermanentTranscriptionException;
use App\Exceptions\Transcription\TransientTranscriptionException;
use App\Models\Call;
use App\Services\Calls\CallAudioStorage;
use App\Services\Transcription\DTO\TranscriptionResult;
use App\Services\Transcription\DTO\TranscriptionSegment;
use App\Support\LanguageCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsTranscriptionClient implements TranscriptionProvider
{
    public function __construct(
        private CallAudioStorage $storage,
        private ActiveTranscriptionProvider $active,
    ) {}

    public function transcribe(Call $call, ?string $languageHint = null): TranscriptionResult
    {
        $credentials = $this->active->current();

        if ($credentials === null || $credentials->apiKey === '') {
            throw new PermanentTranscriptionException(
                'ElevenLabs API key is not configured.',
                'Transcription is temporarily unavailable.',
            );
        }

        if (! $this->storage->exists($call->storage_path)) {
            throw new PermanentTranscriptionException('Call audio file is missing.');
        }

        $path = $this->storage->absolutePath((string) $call->storage_path);
        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new PermanentTranscriptionException('Call audio file could not be read.');
        }

        $endpoint = (string) config('sales-analyzer.transcription.endpoint');
        $model = $credentials->model !== ''
            ? $credentials->model
            : (string) config('sales-analyzer.transcription.model', 'scribe_v2');
        $timeout = (int) config('sales-analyzer.transcription.timeout', 120);
        $filename = $call->original_filename ?: basename($path);
        $apiKey = $credentials->apiKey;
        $forcedLanguage = $this->providerLanguageHint($languageHint);

        try {
            $response = $this->requestSpeechToText(
                $call->id,
                $contents,
                $filename,
                $endpoint,
                $model,
                $timeout,
                $apiKey,
                $forcedLanguage,
            );

            $payload = $this->successfulPayload($call, $response);
            $requestId = $response->header('request-id') ?: $response->header('x-request-id');

            if ($forcedLanguage === null && $this->shouldRetryAsUkrainian($payload)) {
                $this->logLanguageDecision($call, $payload, 'retrying_with_ukr');
                $response = $this->requestSpeechToText(
                    $call->id,
                    $contents,
                    $filename,
                    $endpoint,
                    $model,
                    $timeout,
                    $apiKey,
                    'ukr',
                );
                $payload = $this->successfulPayload($call, $response);
                $requestId = $response->header('request-id') ?: $response->header('x-request-id');
            }
        } finally {
            unset($contents);
        }

        return $this->normalize($call, $payload, $model, $requestId);
    }

    private function requestSpeechToText(
        int $callId,
        string $contents,
        string $filename,
        string $endpoint,
        string $model,
        int $timeout,
        string $apiKey,
        ?string $providerLanguage,
    ): Response {
        $fields = [
            'model_id' => $model,
            'diarize' => config('sales-analyzer.transcription.diarization') ? 'true' : 'false',
            'timestamps_granularity' => config('sales-analyzer.transcription.timestamps') ? 'word' : 'none',
        ];

        if ($providerLanguage !== null) {
            $fields['language_code'] = $providerLanguage;
        }

        try {
            return Http::timeout($timeout)
                ->connectTimeout(15)
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->attach('file', $contents, $filename)
                ->post($endpoint, $fields);
        } catch (ConnectionException $e) {
            Log::warning('ElevenLabs STT connection failed.', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            throw new TransientTranscriptionException('Transcription provider timed out.', 0, $e);
        }
    }

    private function successfulPayload(Call $call, Response $response): array
    {
        $status = $response->status();
        $json = $response->json();

        if ($status === 429 || $status >= 500) {
            Log::warning('ElevenLabs STT transient HTTP error.', [
                'call_id' => $call->id,
                'http_status' => $status,
                'provider_error_code' => $this->errorCode($json),
            ]);

            throw new TransientTranscriptionException('Transcription provider is temporarily unavailable.');
        }

        if ($status >= 400) {
            Log::error('ElevenLabs STT permanent HTTP error.', [
                'call_id' => $call->id,
                'http_status' => $status,
                'provider_error_code' => $this->errorCode($json),
            ]);

            throw new PermanentTranscriptionException('Transcription provider rejected the request.');
        }

        if (! is_array($json)) {
            throw new PermanentTranscriptionException('Transcription provider returned an invalid response.');
        }

        return $json;
    }

    private function providerLanguageHint(?string $languageHint): ?string
    {
        if ($languageHint === null || trim($languageHint) === '') {
            return null;
        }

        if (! LanguageCode::isSupported($languageHint)) {
            throw new PermanentTranscriptionException(
                'Unsupported transcription language hint: '.$languageHint,
                'Could not detect a supported call language.',
            );
        }

        return LanguageCode::toProviderCode($languageHint);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldRetryAsUkrainian(array $payload): bool
    {
        $rawLanguage = isset($payload['language_code']) ? (string) $payload['language_code'] : null;

        if (LanguageCode::isSupported($rawLanguage)) {
            return false;
        }

        $text = trim((string) ($payload['text'] ?? ''));

        return $this->containsUkrainianLetters($text);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logLanguageDecision(Call $call, array $payload, string $decision): void
    {
        Log::error('Transcription language decision.', $this->languageContext($call, $payload, $decision));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function languageContext(Call $call, array $payload, string $decision): array
    {
        $rawLanguage = isset($payload['language_code']) ? (string) $payload['language_code'] : null;
        $text = trim((string) ($payload['text'] ?? ''));
        $probability = isset($payload['language_probability']) ? (float) $payload['language_probability'] : null;

        return [
            'call_id' => $call->id,
            'raw_language_code' => $rawLanguage,
            'normalized_language_code' => LanguageCode::normalize($rawLanguage),
            'language_probability' => $probability,
            'text_script' => $this->textScript($text),
            'has_ukrainian_letters' => $this->containsUkrainianLetters($text),
            'decision' => $decision,
        ];
    }

    private function containsUkrainianLetters(string $text): bool
    {
        return preg_match('/[ієїґІЄЇҐ]/u', $text) === 1;
    }

    private function textScript(string $text): string
    {
        if ($text === '') {
            return 'empty';
        }

        $cyrillic = preg_match('/\p{Cyrillic}/u', $text) === 1;
        $latin = preg_match('/\p{Latin}/u', $text) === 1;

        if ($cyrillic && $latin) {
            return 'mixed';
        }

        if ($cyrillic) {
            return 'cyrillic';
        }

        if ($latin) {
            return 'latin';
        }

        return 'other';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(Call $call, array $payload, string $model, ?string $requestId): TranscriptionResult
    {
        $rawLanguage = isset($payload['language_code']) ? (string) $payload['language_code'] : null;
        $language = LanguageCode::normalize($rawLanguage);
        $probability = isset($payload['language_probability']) ? (float) $payload['language_probability'] : null;

        if (! LanguageCode::isSupported($language)) {
            Log::error(
                'Unsupported transcription language returned by ElevenLabs.',
                $this->languageContext($call, $payload, 'rejected'),
            );

            throw new PermanentTranscriptionException(
                'Unsupported transcription language returned by ElevenLabs: '.($rawLanguage ?: 'unknown'),
                'Could not detect a supported call language.',
            );
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $words = is_array($payload['words'] ?? null) ? $payload['words'] : [];
        $segments = $this->segmentsFromWords($words, $text);
        $duration = isset($payload['audio_duration_secs']) ? (float) $payload['audio_duration_secs'] : $this->durationFromSegments($segments);
        $confidence = $probability;
        $transcriptionId = isset($payload['transcription_id']) ? (string) $payload['transcription_id'] : null;
        $speakers = collect($segments)->pluck('speaker')->unique()->values();

        return new TranscriptionResult(
            provider: 'elevenlabs',
            model: $model,
            language: $language,
            text: $text,
            duration: $duration,
            confidence: $confidence,
            requestId: $requestId ?: $transcriptionId,
            segments: $segments,
            metadata: [
                'request_id' => $requestId ?: $transcriptionId,
                'detected_language' => $rawLanguage,
                'model' => $model,
                'speaker_count' => $speakers->count(),
                'duration_seconds' => $duration,
                'language_probability' => $confidence,
            ],
        );
    }

    /**
     * @param  array<int, mixed>  $words
     * @return array<int, TranscriptionSegment>
     */
    private function segmentsFromWords(array $words, string $fallbackText): array
    {
        $usable = [];

        foreach ($words as $word) {
            if (! is_array($word)) {
                continue;
            }

            $type = (string) ($word['type'] ?? 'word');

            if ($type === 'audio_event') {
                continue;
            }

            $usable[] = $word;
        }

        if ($usable === []) {
            if ($fallbackText === '') {
                return [];
            }

            return [new TranscriptionSegment(0, 0.0, 0.0, $fallbackText)];
        }

        $speakerNumbers = [];

        foreach ($usable as $word) {
            $speakerNumbers[] = $this->speakerNumber($word['speaker_id'] ?? null);
        }

        $min = min($speakerNumbers);
        $offset = $min === 1 ? 1 : 0;

        $segments = [];
        $currentSpeaker = null;
        $buffer = '';
        $start = 0.0;
        $end = 0.0;

        $flush = function () use (&$segments, &$buffer, &$currentSpeaker, &$start, &$end): void {
            $text = trim(preg_replace('/\s+/', ' ', $buffer) ?? '');

            if ($text === '' || $currentSpeaker === null) {
                $buffer = '';

                return;
            }

            $segments[] = new TranscriptionSegment(
                speaker: $currentSpeaker,
                start: $start,
                end: $end,
                text: $text,
            );
            $buffer = '';
        };

        foreach ($usable as $index => $word) {
            $speaker = $this->speakerNumber($word['speaker_id'] ?? null) - $offset;
            $text = (string) ($word['text'] ?? '');
            $wordStart = isset($word['start']) ? (float) $word['start'] : $end;
            $wordEnd = isset($word['end']) ? (float) $word['end'] : $wordStart;

            if ($currentSpeaker === null) {
                $currentSpeaker = $speaker;
                $start = $wordStart;
                $end = $wordEnd;
                $buffer = $text;

                continue;
            }

            if ($speaker !== $currentSpeaker) {
                $flush();
                $currentSpeaker = $speaker;
                $start = $wordStart;
                $end = $wordEnd;
                $buffer = $text;

                continue;
            }

            $buffer .= $text;
            $end = $wordEnd;
            unset($index);
        }

        $flush();

        return $segments;
    }

    private function speakerNumber(mixed $speakerId): int
    {
        if (is_int($speakerId)) {
            return $speakerId;
        }

        if (is_string($speakerId) && preg_match('/(\d+)/', $speakerId, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param  array<int, TranscriptionSegment>  $segments
     */
    private function durationFromSegments(array $segments): ?float
    {
        if ($segments === []) {
            return null;
        }

        return max(array_map(static fn (TranscriptionSegment $segment): float => $segment->end, $segments));
    }

    private function errorCode(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        foreach (['status', 'code', 'detail', 'message'] as $key) {
            if (isset($json[$key]) && is_scalar($json[$key])) {
                $value = substr((string) $json[$key], 0, 200);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
