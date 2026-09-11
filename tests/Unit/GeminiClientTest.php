<?php

namespace Tests\Unit;

use App\Services\Ai\Clients\GeminiClient;
use App\Services\Analysis\SalesAnalysisSchema;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    public function test_generate_content_uses_response_json_schema_for_schema_v3(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => '{"ok":true}']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $schema = SalesAnalysisSchema::jsonSchema();
        $schema['properties']['strengths']['maxItems'] = 12;
        $schema['properties']['strengths']['minItems'] = 0;

        $payload = (new GeminiClient)->completeJson(
            'test-key',
            'gemini-3.7-flash',
            'system',
            'user',
            $schema,
            16384,
        );

        $this->assertSame(['ok' => true], $payload);

        Http::assertSent(function (Request $request) use ($schema): bool {
            $this->assertSame(
                '/v1beta/models/gemini-3.7-flash:generateContent',
                parse_url($request->url(), PHP_URL_PATH),
            );

            $body = $request->data();
            $config = $body['generationConfig'] ?? [];
            $instruction = (string) data_get($body, 'systemInstruction.parts.0.text', '');

            $this->assertArrayHasKey('responseJsonSchema', $config);
            $this->assertArrayNotHasKey('responseSchema', $config);
            $this->assertSame('application/json', $config['responseMimeType'] ?? null);

            $wire = $config['responseJsonSchema'];
            $this->assertSame('object', $wire['type'] ?? null);
            $this->assertFalse($wire['additionalProperties']);
            $this->assertContains('strengths', $wire['required']);
            $this->assertContains('overall_score', $wire['required']);
            $this->assertSame($wire['required'], array_values(array_unique($wire['required'])));
            $this->assertSame('integer', $wire['properties']['overall_score']['type']);
            $this->assertSame(SalesAnalysisSchema::OUTCOMES, $wire['properties']['call_outcome']['enum']);
            $this->assertSame('array', $wire['properties']['strengths']['type']);
            $this->assertSame('object', $wire['properties']['strengths']['items']['type']);
            $this->assertSame(['text'], $wire['properties']['strengths']['items']['required']);
            $this->assertArrayHasKey('text', $wire['properties']['strengths']['items']['properties']);

            $encoded = $instruction."\n".(string) json_encode($schema);
            $this->assertStringContainsString('"additionalProperties"', $encoded);
            $this->assertStringContainsString('["integer","null"]', $encoded);
            $this->assertStringContainsString('"required"', $encoded);
            $this->assertStringContainsString('"enum"', $encoded);
            $this->assertStringContainsString('"maxItems":12', $instruction.(string) json_encode($schema));
            $this->assertStringContainsString('"minItems":0', $instruction.(string) json_encode($schema));
            $this->assertStringContainsString(json_encode($schema), $instruction);
            $this->assertStringNotContainsString('"responseSchema"', $request->body());
            $this->assertStringNotContainsString('test-key', $request->body());

            return true;
        });
    }

    public function test_generation_config_never_uses_legacy_response_schema(): void
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['items'],
            'properties' => [
                'score' => ['type' => ['integer', 'null']],
                'kind' => ['type' => 'string', 'enum' => ['a', 'b']],
                'items' => [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => 8,
                    'items' => ['type' => 'string'],
                ],
            ],
        ];

        $config = GeminiClient::generationConfig($schema, 4096);

        $this->assertArrayHasKey('responseJsonSchema', $config);
        $this->assertArrayNotHasKey('responseSchema', $config);
        $this->assertSame('application/json', $config['responseMimeType']);
        $this->assertFalse($config['responseJsonSchema']['additionalProperties']);
        $this->assertSame(['items'], $config['responseJsonSchema']['required']);
        $this->assertSame('integer', $config['responseJsonSchema']['properties']['score']['type']);
        $this->assertSame(['a', 'b'], $config['responseJsonSchema']['properties']['kind']['enum']);
        $this->assertSame('array', $config['responseJsonSchema']['properties']['items']['type']);
    }

    public function test_schema_v3_required_list_is_unique(): void
    {
        $required = SalesAnalysisSchema::jsonSchema()['required'];

        $this->assertSame($required, array_values(array_unique($required)));
        $this->assertContains('customer_intent_confidence', $required);
        $this->assertContains('executive_summary', $required);
    }
}
