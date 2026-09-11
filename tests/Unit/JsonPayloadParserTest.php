<?php

namespace Tests\Unit;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
use App\Services\Ai\JsonPayloadParser;
use Tests\TestCase;

class JsonPayloadParserTest extends TestCase
{
    public function test_valid_object_is_parsed(): void
    {
        $decoded = JsonPayloadParser::parse('{"overall_score": 70, "summary": "ok"}');

        $this->assertSame(70, $decoded['overall_score']);
    }

    public function test_truncated_json_is_retry_safe(): void
    {
        try {
            JsonPayloadParser::parse('{"overall_score": 70, "summary": "The seller started well and then');
            $this->fail('Truncated JSON should throw.');
        } catch (TransientAnalysisException $e) {
            $this->assertStringContainsString('truncated', strtolower($e->getMessage()));
            $this->assertStringContainsString('Retry the analysis', $e->getMessage());
        }
    }

    public function test_invalid_json_is_retry_safe(): void
    {
        try {
            JsonPayloadParser::parse('{"overall_score":}');
            $this->fail('Invalid JSON should throw.');
        } catch (TransientAnalysisException $e) {
            $this->assertStringContainsString('invalid JSON', $e->getMessage());
        }
    }

    public function test_json_array_is_rejected_permanently(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        JsonPayloadParser::parse('[{"overall_score": 1}]');
    }
}
