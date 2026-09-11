<?php

namespace App\Services\Ai\Clients;

use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\JsonPayloadParser;
use App\Services\Ai\ProviderHttp;
use App\Services\Analysis\SalesAnalysisSchema;
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
        int $maxOutputTokens = 16384,
    ): array {
        $modelId = str_starts_with($model, 'models/') ? substr($model, 7) : $model;

        try {
            $response = Http::timeout(180)
                ->connectTimeout(15)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$modelId.':generateContent', [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => $system."\n\nReturn only JSON matching this schema:\n".json_encode($jsonSchema),
                        ]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $user]]],
                    ],
                    'generationConfig' => self::generationConfig($jsonSchema, $maxOutputTokens),
                ]);
        } catch (ConnectionException $e) {
            throw ProviderHttp::wrapConnection($e);
        }

        ProviderHttp::throwForStatus($response, 'gemini');

        $text = (string) $response->json('candidates.0.content.parts.0.text', '');

        return self::normalizePayload(JsonPayloadParser::parse($text));
    }

    /**
     * Gemini generateContent uses JSON Schema (`responseJsonSchema`), never OpenAPI `responseSchema`.
     *
     * The full v3 graph cannot be compiled by generateContent (nullable unions and nested
     * complexity). The wire schema is a shallow projection of v3 required keys and types.
     * The complete JSON Schema is also attached in the prompt and enforced by the validator.
     *
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public static function generationConfig(array $jsonSchema, int $maxOutputTokens): array
    {
        return [
            'temperature' => 0.2,
            'maxOutputTokens' => $maxOutputTokens,
            'responseMimeType' => 'application/json',
            'responseJsonSchema' => self::forGeminiWire($jsonSchema),
        ];
    }

    /**
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public static function forGeminiWire(array $jsonSchema): array
    {
        $properties = [];
        foreach ($jsonSchema['properties'] ?? [] as $name => $schema) {
            if (! is_array($schema)) {
                continue;
            }
            $properties[$name] = self::shallowProperty($schema);
        }

        $required = [];
        foreach ($jsonSchema['required'] ?? array_keys($properties) as $name) {
            if (is_string($name) && isset($properties[$name])) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_values(array_unique($required)),
            'properties' => $properties,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function shallowProperty(array $schema): array
    {
        $type = $schema['type'] ?? 'object';
        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, fn (mixed $item): bool => $item !== 'null'));
            $type = $nonNull[0] ?? 'object';
        }

        $wire = ['type' => $type];

        if ($type === 'string' && isset($schema['enum']) && is_array($schema['enum'])) {
            $wire['enum'] = $schema['enum'];
        }

        if (($type === 'integer' || $type === 'number') && isset($schema['minimum'])) {
            $wire['minimum'] = $schema['minimum'];
        }
        if (($type === 'integer' || $type === 'number') && isset($schema['maximum'])) {
            $wire['maximum'] = $schema['maximum'];
        }

        if ($type === 'array') {
            $wire['items'] = [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],
                ],
                'required' => ['text'],
            ];
        }

        if ($type === 'object') {
            $wire['additionalProperties'] = true;
        }

        return $wire;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizePayload(array $payload): array
    {
        $payload = self::restoreEventTypes($payload);

        if (isset($payload['timeline']) && is_array($payload['timeline'])) {
            foreach ($payload['timeline'] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $text = is_string($item['text'] ?? null) ? $item['text'] : '';
                $type = $item['type'] ?? $item['event_type'] ?? '';
                if (! in_array($type, SalesAnalysisSchema::TIMELINE_TYPES, true)) {
                    $type = 'turning_point';
                }

                $item['type'] = $type;
                if (! filled($item['title'] ?? null)) {
                    $item['title'] = $text !== '' ? mb_substr($text, 0, 80) : 'Timeline moment';
                }

                $payload['timeline'][$index] = $item;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function restoreEventTypes(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::restoreEventTypes($value);
            }
        }

        if (array_key_exists('event_type', $payload) && ! array_key_exists('type', $payload)) {
            $payload['type'] = $payload['event_type'];
        }

        return $payload;
    }
}
