<?php

namespace App\Services\Transcription;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ElevenLabsConnectionChecker
{
    public function check(string $apiKey): void
    {
        $endpoint = (string) config('sales-analyzer.transcription.endpoint');
        $model = (string) config('sales-analyzer.transcription.model', 'scribe_v2');

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->asMultipart()
                ->post($endpoint, [
                    'model_id' => $model,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the transcription provider.');
        } catch (Throwable $e) {
            throw new RuntimeException('Transcription connection check failed.');
        }

        if ($this->accessConfirmed($response)) {
            return;
        }

        throw new RuntimeException($this->errorMessage($response));
    }

    private function accessConfirmed(Response $response): bool
    {
        if ($response->successful()) {
            return true;
        }

        // No audio is sent. 400/422 after auth means the key was accepted.
        if (in_array($response->status(), [400, 422], true)) {
            $code = $this->detailStatus($response);

            return ! in_array($code, [
                'missing_permissions',
                'invalid_api_key',
                'authentication_error',
                'authorization_error',
            ], true);
        }

        return false;
    }

    private function errorMessage(Response $response): string
    {
        $status = $response->status();
        $code = $this->detailStatus($response);

        if ($code === 'missing_permissions') {
            $permission = $this->missingPermission($response);

            if ($permission === 'speech_to_text') {
                return 'This API key is missing the speech_to_text permission. Enable Speech to Text for the key in the ElevenLabs dashboard.';
            }

            if (is_string($permission) && preg_match('/^[a-z0-9_]+$/', $permission) === 1) {
                return 'This API key is missing the '.$permission.' permission.';
            }

            return 'This API key is missing a required ElevenLabs permission.';
        }

        if ($status === 401 || $status === 403 || $code === 'invalid_api_key') {
            return 'Transcription credentials were rejected.';
        }

        return 'Transcription connection check failed (HTTP '.$status.').';
    }

    private function detailStatus(Response $response): ?string
    {
        $detail = $response->json('detail');

        if (is_array($detail) && isset($detail['status']) && is_scalar($detail['status'])) {
            return (string) $detail['status'];
        }

        return null;
    }

    private function missingPermission(Response $response): ?string
    {
        $message = $response->json('detail.message');

        if (! is_string($message) || preg_match('/missing the permission ([a-z0-9_]+)/i', $message, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
