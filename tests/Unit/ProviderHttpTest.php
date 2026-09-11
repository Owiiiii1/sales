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
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('additionalProperties', $e->getMessage());
            $this->assertStringNotContainsString('(400)', $e->getMessage());
        }
    }
}
