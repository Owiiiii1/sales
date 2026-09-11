<?php

namespace Tests\Unit;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Services\Ai\ProviderHttp;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class ProviderHttpTest extends TestCase
{
    public function test_gemini_400_uses_error_message_instead_of_numeric_code(): void
    {
        $response = new Response(new PsrResponse(
            400,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => [
                    'code' => 400,
                    'message' => 'Unknown name "additionalProperties" at generation_config.response_schema',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ]),
        ));

        try {
            ProviderHttp::throwForStatus($response, 'gemini');
            $this->fail('HTTP 400 should be a permanent analysis failure.');
        } catch (PermanentAnalysisException $e) {
            $this->assertSame(
                'Gemini HTTP 400 INVALID_ARGUMENT: Unknown name "additionalProperties" at generation_config.response_schema',
                $e->getMessage(),
            );
            $this->assertStringNotContainsString('(400)', $e->getMessage());
            $this->assertSame('Analysis failed. Please try again.', $e->publicMessage());
        }
    }

    public function test_gemini_invalid_json_schema_error_includes_status_and_message(): void
    {
        $response = new Response(new PsrResponse(
            400,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid JSON schema',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ]),
        ));

        try {
            ProviderHttp::throwForStatus($response, 'gemini');
            $this->fail('HTTP 400 should be a permanent analysis failure.');
        } catch (PermanentAnalysisException $e) {
            $this->assertStringContainsString('INVALID_ARGUMENT', $e->getMessage());
            $this->assertStringContainsString('Invalid JSON schema', $e->getMessage());
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertDoesNotMatchRegularExpression('/\(400\)/', $e->getMessage());
            $this->assertSame('Analysis failed. Please try again.', $e->publicMessage());
        }
    }
}
