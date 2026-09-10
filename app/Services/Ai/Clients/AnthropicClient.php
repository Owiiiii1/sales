<?php

namespace App\Services\Ai\Clients;

use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\JsonPayloadParser;
use App\Services\Ai\ProviderHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicClient implements AiProviderClient
{
    public function provider(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Claude';
    }

    public function listModels(string $apiKey): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->get('https://api.anthropic.com/v1/models');

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('error.message')
                    ?: 'Anthropic request failed with status '.$response->status()
            );
        }

        $models = collect($response->json('data', []))
            ->map(fn (array $item): array => [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['display_name'] ?? $item['id'] ?? ''),
            ])
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();

        if ($models === []) {
            throw new RuntimeException('Anthropic returned no models for this API key.');
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
        int $maxOutputTokens = 16384,
    ): array {
        try {
            $response = Http::timeout(180)
                ->connectTimeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->acceptJson()
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $maxOutputTokens,
                    'temperature' => 0.2,
                    'system' => $system."\n\nReturn only JSON matching this schema:\n".json_encode($jsonSchema),
                    'messages' => [
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw ProviderHttp::wrapConnection($e);
        }

        ProviderHttp::throwForStatus($response, 'anthropic');

        $blocks = $response->json('content', []);
        $text = '';
        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return JsonPayloadParser::parse($text);
    }
}
