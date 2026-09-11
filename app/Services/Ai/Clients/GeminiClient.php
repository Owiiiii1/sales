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
     * The full v3 graph cannot be compiled. Live probes on gemini-3.7-flash accept
     * slim nested items for critical_mistakes, better_phrases, coaching_priorities,
     * and scorecard criteria. missed_signals and timeline stay a shallow `{text}`
     * projection; text is mapped to signal/title after parse.
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
     * @return array<int, string>
     */
    public static function detailedArrayKeys(): array
    {
        return [
            'critical_mistakes',
            'better_phrases',
            'coaching_priorities',
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

            $properties[$name] = match ($name) {
                'critical_mistakes' => self::arrayOf(self::criticalMistakeItem()),
                'better_phrases' => self::arrayOf(self::betterPhraseItem()),
                'coaching_priorities' => self::arrayOf(self::coachingPriorityItem()),
                'company_specific' => self::companySpecificWire(),
                default => self::shallowProperty($schema),
            };
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
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function arrayOf(array $item): array
    {
        return [
            'type' => 'array',
            'items' => $item,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function criticalMistakeItem(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'timestamp_seconds' => ['type' => 'number'],
                'mistake' => ['type' => 'string'],
                'impact' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'why' => ['type' => 'string'],
                'better_action' => ['type' => 'string'],
                'example_phrase' => ['type' => 'string'],
            ],
            'required' => ['mistake'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function betterPhraseItem(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'timestamp_seconds' => ['type' => 'number'],
                'original' => ['type' => 'string'],
                'problem' => ['type' => 'string'],
                'better' => ['type' => 'string'],
                'why_better' => ['type' => 'string'],
            ],
            'required' => ['original', 'better'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function coachingPriorityItem(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'skill' => ['type' => 'string'],
                'why' => ['type' => 'string'],
                'evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                'practice' => ['type' => 'string'],
                'success_criteria' => ['type' => 'string'],
            ],
            'required' => ['priority', 'skill'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function companySpecificWire(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => true,
            'properties' => [
                'scorecard' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => [
                        'criteria' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'key' => ['type' => 'string'],
                                    'score' => ['type' => 'integer'],
                                    'max_score' => ['type' => 'integer'],
                                    'applicable' => ['type' => 'boolean'],
                                    'summary' => ['type' => 'string'],
                                ],
                                'required' => ['key'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function shallowProperty(array $schema): array
    {
        $type = self::scalarType($schema['type'] ?? 'object');
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

    private static function scalarType(mixed $type): string
    {
        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, fn (mixed $item): bool => $item !== 'null'));
            $type = $nonNull[0] ?? 'object';
        }

        return is_string($type) ? $type : 'object';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizePayload(array $payload): array
    {
        $payload = self::restoreEventTypes($payload);
        $payload = self::promoteTextField($payload, 'timeline', 'title');
        $payload = self::promoteTextField($payload, 'missed_signals', 'signal');

        if (isset($payload['timeline']) && is_array($payload['timeline'])) {
            foreach ($payload['timeline'] as $index => $item) {
                if (! is_array($item) || ! filled($item['title'] ?? null)) {
                    continue;
                }

                $type = $item['type'] ?? '';
                if (! in_array($type, SalesAnalysisSchema::TIMELINE_TYPES, true)) {
                    $item['type'] = 'turning_point';
                    $payload['timeline'][$index] = $item;
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function promoteTextField(array $payload, string $key, string $target): array
    {
        if (! isset($payload[$key]) || ! is_array($payload[$key])) {
            return $payload;
        }

        foreach ($payload[$key] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = is_string($item['text'] ?? null) ? $item['text'] : '';
            if (! filled($item[$target] ?? null) && $text !== '') {
                $item[$target] = $text;
            }

            $payload[$key][$index] = $item;
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
