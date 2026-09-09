<?php

namespace Tests\Unit;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Services\Analysis\SalesAnalysisResultValidator;
use Database\Factories\SalesAnalysisFactory;
use Tests\TestCase;

class SalesAnalysisResultValidatorTest extends TestCase
{
    public function test_valid_payload_is_accepted(): void
    {
        $result = app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(),
            'openai',
            'gpt-4o-mini',
        );

        $this->assertSame(72, $result->overallScore);
        $this->assertSame('follow_up', $result->payload['call_outcome']);
        $this->assertFalse($result->payload['sections']['pricing_negotiation']['applicable']);
    }

    public function test_missing_key_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        $payload = SalesAnalysisFactory::validPayload();
        unset($payload['summary']);
        app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini');
    }

    public function test_invalid_score_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(['overall_score' => -1]),
            'openai',
            'gpt-4o-mini',
        );
    }

    public function test_invalid_outcome_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(['call_outcome' => 'victory']),
            'openai',
            'gpt-4o-mini',
        );
    }

    public function test_invalid_speaker_role_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(['speaker_roles' => ['0' => 'boss']]),
            'openai',
            'gpt-4o-mini',
        );
    }
}
