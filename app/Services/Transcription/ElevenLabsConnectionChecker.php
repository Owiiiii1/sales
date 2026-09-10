<?php

namespace App\Services\Transcription;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ElevenLabsConnectionChecker
{
    public function check(string $apiKey): void
    {
        $endpoint = (string) config('sales-analyzer.transcription.user_endpoint', 'https://api.elevenlabs.io/v1/user');

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the transcription provider.');
        } catch (Throwable $e) {
            throw new RuntimeException('Transcription connection check failed.');
        }

        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new RuntimeException('Transcription credentials were rejected.');
        }

        throw new RuntimeException('Transcription connection check failed (HTTP '.$status.').');
    }
}
