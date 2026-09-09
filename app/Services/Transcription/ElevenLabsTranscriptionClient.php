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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsTranscriptionClient implements TranscriptionProvider
{
    public function __construct(private CallAudioStorage $storage) {}

    public function transcribe(Call $call): TranscriptionResult
    {
        $apiKey = (string) config('sales-analyzer.transcription.api_key');

        if ($apiKey === '') {
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
        $model = (string) config('sales-analyzer.transcription.model');
        $timeout = (int) config('sales-analyzer.transcription.timeout', 120);
        $filename = $call->original_filename ?: basename($path);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(15)
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->attach('file', $contents, $filename)
                ->post($endpoint, [
                    'model_id' => $model,
                    'diarize' => config('sales-analyzer.transcription.diarization') ? 'true' : 'false',
                    'timestamps_granularity' => config('sales-analyzer.transcription.timestamps') ? 'word' : 'none',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('ElevenLabs STT connection failed.', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            throw new TransientTranscriptionException('Transcription provider timed out.', 0, $e);
        } finally {
            unset($contents);
        }

        $status = $response->status();
        $requestId = $response->header('request-id') ?: $response->header('x-request-id');

        if ($status === 429 || $status >= 500) {
            Log::warning('ElevenLabs STT transient HTTP error.', [
                'call_id' => $call->id,
                'http_status' => $status,
                'provider_error_code' => $this->errorCode($response->json()),
            ]);

            throw new TransientTranscriptionException('Transcription provider is temporarily unavailable.');
        }

        if ($status >= 400) {
            Log::error('ElevenLabs STT permanent HTTP error.', [
                'call_id' => $call->id,
                'http_status' => $status,
                'provider_error_code' => $this->errorCode($response->json()),
            ]);

            throw new PermanentTranscriptionException('Transcription provider rejected the request.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new PermanentTranscriptionException('Transcription provider returned an invalid response.');
        }

        return $this->normalize($payload, $model, $requestId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(array $payload, string $model, ?string $requestId): TranscriptionResult
    {
        $rawLanguage = isset($payload['language_code']) ? (string) $payload['language_code'] : null;
        $language = LanguageCode::normalize($rawLanguage);

        if (! LanguageCode::isSupported($language)) {
            throw new PermanentTranscriptionException(
                'Unsupported transcription language: '.($rawLanguage ?: 'unknown'),
                'This language is not supported yet.',
            );
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $words = is_array($payload['words'] ?? null) ? $payload['words'] : [];
        $segments = $this->segmentsFromWords($words, $text);
        $duration = isset($payload['audio_duration_secs']) ? (float) $payload['audio_duration_secs'] : $this->durationFromSegments($segments);
        $confidence = isset($payload['language_probability']) ? (float) $payload['language_probability'] : null;
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

    /**
     * @param  mixed  $json
     */
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
