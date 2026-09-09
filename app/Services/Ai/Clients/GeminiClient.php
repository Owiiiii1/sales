<?php

namespace App\Services\Ai\Clients;

use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\JsonPayloadParser;
use App\Services\Ai\ProviderHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient implements AiProviderClient
{
    public function provider(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Gemini';
    }

    public function listModels(string $apiKey): array
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                'key' => $apiKey,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('error.message')
                    ?: 'Gemini request failed with status '.$response->status()
            );
        }

        $models = collect($response->json('models', []))
            ->map(function (array $item): array {
                $rawName = (string) ($item['name'] ?? '');
                $id = str_starts_with($rawName, 'models/')
                    ? substr($rawName, 7)
                    : $rawName;

                return [
                    'id' => $id,
                    'name' => (string) ($item['displayName'] ?? $id),
                ];
            })
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();

        if ($models === []) {
            throw new RuntimeException('Gemini returned no models for this API key.');
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function completeJson(
        string $apiKey,
        string $model,
        string $system,
        string $user,
        array $jsonSchema,
    ): array {
        $modelId = str_starts_with($model, 'models/') ? substr($model, 7) : $model;

        try {
            $response = Http::timeout(90)
                ->connectTimeout(15)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$modelId.':generateContent', [
                    'systemInstruction' => [
                        'parts' => [['text' => $system]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $user]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $jsonSchema,
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw ProviderHttp::wrapConnection($e);
        }

        ProviderHttp::throwForStatus($response, 'gemini');

        $text = (string) $response->json('candidates.0.content.parts.0.text', '');

        return JsonPayloadParser::parse($text);
    }
}
