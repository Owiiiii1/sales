<?php

namespace App\Services\Ai\Clients;

use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\JsonPayloadParser;
use App\Services\Ai\ProviderHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient implements AiProviderClient
{
    public function provider(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function listModels(string $apiKey): array
    {
        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->acceptJson()
            ->get('https://api.openai.com/v1/models');

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('error.message')
                    ?: 'OpenAI request failed with status '.$response->status()
            );
        }

        $models = collect($response->json('data', []))
            ->map(fn (array $item): array => [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['id'] ?? ''),
            ])
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();

        if ($models === []) {
            throw new RuntimeException('OpenAI returned no models for this API key.');
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
        try {
            $response = Http::timeout(90)
                ->connectTimeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'sales_analysis',
                            'strict' => false,
                            'schema' => $jsonSchema,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw ProviderHttp::wrapConnection($e);
        }

        ProviderHttp::throwForStatus($response, 'openai');

        $content = (string) $response->json('choices.0.message.content', '');

        return JsonPayloadParser::parse($content);
    }
}
