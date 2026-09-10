<?php

namespace Tests\Unit;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\SalesAnalysisResultValidator;
use App\Services\Analysis\SalesAnalysisSchema;
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

    public function test_generic_payload_may_omit_company_specific(): void
    {
        $payload = SalesAnalysisFactory::validPayload();
        unset($payload['company_specific']);

        $result = app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini');

        $this->assertFalse($result->companyContextUsed);
        $this->assertSame(SalesAnalysisSchema::emptyCompanySpecific(), $result->payload['company_specific']);
    }

    public function test_company_context_requires_company_specific(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        $payload = SalesAnalysisFactory::validPayload();
        unset($payload['company_specific']);
        app(SalesAnalysisResultValidator::class)->validate(
            $payload,
            'openai',
            'gpt-4o-mini',
            new AnalysisContext(companyContextUsed: true),
        );
    }

    public function test_unknown_and_duplicate_scorecard_keys_are_rejected(): void
    {
        $context = $this->scorecardContext();
        $payload = $this->companyPayload([
            ['key' => 'unknown_key', 'score' => 10, 'max_score' => 100, 'applicable' => true, 'summary' => '', 'evidence' => [], 'critical_failure' => false],
        ]);

        try {
            app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini', $context);
            $this->fail('Unknown criterion should be rejected.');
        } catch (PermanentAnalysisException $e) {
            $this->assertStringContainsString('Unknown scorecard criterion', $e->getMessage());
        }

        $duplicate = $this->companyPayload([
            $this->criterion('system_age', 80),
            $this->criterion('system_age', 70),
        ]);

        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate($duplicate, 'openai', 'gpt-4o-mini', $context);
    }

    public function test_weighted_score_overwrites_llm_total_and_excludes_non_applicable(): void
    {
        $context = $this->scorecardContext();
        $payload = $this->companyPayload([
            $this->criterion('system_age', 80),
            $this->criterion('estimate', 10, applicable: false),
        ]);
        $payload['company_specific']['scorecard']['total_score'] = 99;

        $result = app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini', $context);

        $this->assertSame(72, $result->overallScore);
        $this->assertSame(80, $result->companyScorecardScore);
        $this->assertSame(80, $result->payload['company_specific']['scorecard']['total_score']);
        $this->assertFalse($result->payload['company_specific']['scorecard']['criteria'][1]['applicable']);
    }

    public function test_company_context_used_mismatch_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            $this->companyPayload([$this->criterion('system_age', 80), $this->criterion('estimate', 50)]),
            'openai',
            'gpt-4o-mini',
            new AnalysisContext(companyContextUsed: false),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function companyPayload(array $criteria): array
    {
        return SalesAnalysisFactory::validPayload([
            'company_context_used' => true,
            'company_specific' => [
                'script_adherence' => [
                    'applicable' => true,
                    'score' => 80,
                    'summary' => 'Most steps were followed.',
                    'issues' => [],
                ],
                'mandatory_questions' => [
                    'asked' => ['system age'],
                    'missed' => [],
                ],
                'forbidden_claims' => [
                    'violations' => [],
                ],
                'objection_handling' => [
                    'matched' => [],
                ],
                'offering_accuracy' => [
                    'issues' => [],
                ],
                'scorecard' => [
                    'total_score' => 0,
                    'criteria' => $criteria,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function criterion(string $key, int $score, bool $applicable = true): array
    {
        return [
            'key' => $key,
            'score' => $applicable ? $score : null,
            'max_score' => 100,
            'applicable' => $applicable,
            'summary' => '',
            'evidence' => [],
            'critical_failure' => false,
        ];
    }

    private function scorecardContext(): AnalysisContext
    {
        return new AnalysisContext(
            companyContextUsed: true,
            companyContextHash: 'abc',
            scorecardId: 1,
            scorecardSnapshot: [
                'name' => 'Inbound',
                'criteria' => [
                    ['key' => 'system_age', 'weight' => 40, 'max_score' => 100],
                    ['key' => 'estimate', 'weight' => 60, 'max_score' => 100],
                ],
            ],
            snapshot: ['company' => ['id' => 1]],
        );
    }
}
