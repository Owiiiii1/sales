<?php

namespace Tests\Unit;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Exceptions\Analysis\TransientAnalysisException;
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
        $this->assertSame(3, $result->schemaVersion);
        $this->assertSame('The seller opened well, found interest, then left without a booked next step.', $result->payload['executive_summary']['one_sentence']);
        $this->assertFalse($result->payload['negotiation']['applicable']);
        $this->assertNull($result->payload['negotiation']['score']);
        $this->assertCount(1, $result->payload['coaching_priorities']);
        $this->assertSame('Can we book a 20-minute call on Thursday at 10?', $result->payload['better_phrases'][0]['better']);
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

    public function test_empty_critical_mistake_is_skipped(): void
    {
        $result = app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload([
                'critical_mistakes' => [
                    [
                        'timestamp_seconds' => null,
                        'mistake' => '',
                        'impact' => 'high',
                        'why' => '',
                        'better_action' => '',
                        'example_phrase' => '',
                    ],
                    [
                        'timestamp_seconds' => 12,
                        'mistake' => 'Left without a next step.',
                        'impact' => 'high',
                        'why' => 'Interest was explicit.',
                        'better_action' => 'Offer two times.',
                        'example_phrase' => 'Thursday at 10?',
                    ],
                ],
            ]),
            'openai',
            'gpt-4o-mini',
        );

        $this->assertCount(1, $result->payload['critical_mistakes']);
        $this->assertSame('Left without a next step.', $result->payload['critical_mistakes'][0]['mistake']);
    }

    public function test_empty_better_phrase_and_coaching_items_are_skipped(): void
    {
        $result = app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload([
                'better_phrases' => [
                    ['original' => '', 'problem' => '', 'better' => '', 'why_better' => ''],
                    [
                        'timestamp_seconds' => 4,
                        'original' => 'I will be in touch.',
                        'problem' => 'No date.',
                        'better' => 'Can we book Thursday at 10?',
                        'why_better' => 'Creates a commitment.',
                    ],
                ],
                'coaching_priorities' => [
                    ['priority' => 1, 'skill' => '', 'why' => '', 'evidence' => [], 'practice' => '', 'success_criteria' => ''],
                    [
                        'priority' => 2,
                        'skill' => 'Closing',
                        'why' => 'No next step.',
                        'evidence' => ['I will be in touch.'],
                        'practice' => 'Offer two times.',
                        'success_criteria' => 'A dated meeting.',
                    ],
                ],
            ]),
            'openai',
            'gpt-4o-mini',
        );

        $this->assertCount(1, $result->payload['better_phrases']);
        $this->assertSame('Can we book Thursday at 10?', $result->payload['better_phrases'][0]['better']);
        $this->assertCount(1, $result->payload['coaching_priorities']);
        $this->assertSame('Closing', $result->payload['coaching_priorities'][0]['skill']);
    }

    public function test_missing_majority_scorecard_criteria_fails_company_analysis(): void
    {
        $this->expectException(TransientAnalysisException::class);
        $this->expectExceptionMessage('majority of expected criteria are missing');

        app(SalesAnalysisResultValidator::class)->validate(
            $this->companyPayload([]),
            'openai',
            'gpt-4o-mini',
            $this->scorecardContext(),
        );
    }

    public function test_mapped_scorecard_criteria_are_accepted_with_names(): void
    {
        $context = $this->scorecardContext();
        $context->scorecardSnapshot['criteria'][0]['name'] = 'System age';
        $context->scorecardSnapshot['criteria'][1]['name'] = 'Estimate';

        $result = app(SalesAnalysisResultValidator::class)->validate(
            $this->companyPayload([
                $this->criterion('system_age', 80),
                $this->criterion('estimate', 60),
            ]),
            'openai',
            'gpt-4o-mini',
            $context,
        );

        $this->assertSame('System age', $result->payload['company_specific']['scorecard']['criteria'][0]['name']);
        $this->assertSame(80, $result->payload['company_specific']['scorecard']['criteria'][0]['score']);
        $this->assertSame('Estimate', $result->payload['company_specific']['scorecard']['criteria'][1]['name']);
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
        $this->assertSame(80, $result->payload['company_specific']['scorecard']['weighted_score']);
        $this->assertSame([], $result->payload['company_specific']['scorecard']['triggered_caps']);
        $this->assertFalse($result->payload['company_specific']['scorecard']['criteria'][1]['applicable']);
    }

    public function test_snapshot_caps_are_applied_and_llm_total_is_ignored(): void
    {
        $context = $this->scorecardContext();
        $context->scorecardSnapshot['caps'] = [[
            'id' => 1,
            'name' => 'Critical factual error',
            'criterion_key' => 'system_age',
            'trigger_type' => 'criterion_critical_failure',
            'max_total_score' => 70,
            'description' => 'Outdated event date',
        ]];
        $payload = $this->companyPayload([
            $this->criterion('system_age', 92, applicable: true, critical: true),
            $this->criterion('estimate', 92),
        ]);
        $payload['company_specific']['scorecard']['total_score'] = 99;

        $result = app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini', $context);

        $this->assertSame(92, $result->payload['company_specific']['scorecard']['weighted_score']);
        $this->assertSame(70, $result->payload['company_specific']['scorecard']['total_score']);
        $this->assertSame(70, $result->companyScorecardScore);
        $this->assertSame('Critical factual error', $result->payload['company_specific']['scorecard']['triggered_caps'][0]['name']);
    }

    public function test_large_valid_v3_payload_is_accepted(): void
    {
        $timeline = [];
        for ($i = 0; $i < 15; $i++) {
            $timeline[] = [
                'timestamp_seconds' => $i * 10,
                'type' => 'positive',
                'title' => 'Moment '.$i,
                'description' => str_repeat('Detail about this moment. ', 20),
                'speaker' => 0,
                'quote' => 'Original quote stays in English '.$i,
            ];
        }

        $payload = SalesAnalysisFactory::validPayload([
            'timeline' => $timeline,
            'summary' => str_repeat('The seller kept the original quote. ', 80),
        ]);

        $result = app(SalesAnalysisResultValidator::class)->validate($payload, 'openai', 'gpt-4o-mini');

        $this->assertCount(15, $result->payload['timeline']);
        $this->assertSame(3, $result->schemaVersion);
        $this->assertStringContainsString('Original quote stays in English', $result->payload['timeline'][0]['quote']);
    }

    public function test_max_output_tokens_have_provider_caps(): void
    {
        $caps = config('sales-analyzer.analysis.max_output_tokens');

        $this->assertSame(16384, $caps['default']);
        $this->assertSame(32768, $caps['providers']['openai']);
        $this->assertSame(16384, $caps['providers']['anthropic']);
        $this->assertSame(16384, $caps['providers']['gemini']);
        $this->assertSame(50000, (int) config('sales-analyzer.analysis.context_budget_characters'));
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

    public function test_malformed_v3_enums_are_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload([
                'conversation_control' => [
                    'score' => 50,
                    'who_led' => 'both',
                    'summary' => 'x',
                    'loss_of_control_moments' => [],
                    'recovery_moments' => [],
                ],
            ]),
            'openai',
            'gpt-4o-mini',
        );
    }

    public function test_negative_timestamps_are_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload([
                'timeline' => [[
                    'timestamp_seconds' => -1,
                    'type' => 'positive',
                    'title' => 'Bad',
                    'description' => '',
                    'speaker' => 0,
                    'quote' => null,
                ]],
            ]),
            'openai',
            'gpt-4o-mini',
        );
    }

    public function test_excessive_arrays_are_rejected_and_modest_overflow_is_sliced(): void
    {
        $six = [];
        for ($i = 1; $i <= 6; $i++) {
            $six[] = [
                'priority' => min($i, 5),
                'skill' => 'Skill '.$i,
                'why' => 'Why '.$i,
                'evidence' => ['quote'],
                'practice' => 'Practice '.$i,
                'success_criteria' => 'Done',
            ];
        }

        $sliced = app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(['coaching_priorities' => $six]),
            'openai',
            'gpt-4o-mini',
        );
        $this->assertCount(5, $sliced->payload['coaching_priorities']);

        $tooMany = [];
        for ($i = 1; $i <= 12; $i++) {
            $tooMany[] = [
                'priority' => 1,
                'skill' => 'Skill '.$i,
                'why' => 'Why',
                'evidence' => [],
                'practice' => 'Practice',
                'success_criteria' => '',
            ];
        }

        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload(['coaching_priorities' => $tooMany]),
            'openai',
            'gpt-4o-mini',
        );
    }

    public function test_invalid_objection_category_is_rejected(): void
    {
        $this->expectException(PermanentAnalysisException::class);
        app(SalesAnalysisResultValidator::class)->validate(
            SalesAnalysisFactory::validPayload([
                'objection_map' => [[
                    'timestamp_seconds' => 10,
                    'objection' => 'Too expensive',
                    'category' => 'feelings',
                    'explicit_or_implicit' => 'explicit',
                    'seller_response' => 'Discount',
                    'response_quality' => 20,
                    'what_was_good' => '',
                    'what_was_missing' => 'Value',
                    'better_response' => 'Ask what too expensive means.',
                    'resolved' => false,
                ]],
            ]),
            'openai',
            'gpt-4o-mini',
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
    private function criterion(string $key, int $score, bool $applicable = true, bool $critical = false): array
    {
        return [
            'key' => $key,
            'score' => $applicable ? $score : null,
            'max_score' => 100,
            'applicable' => $applicable,
            'summary' => '',
            'evidence' => [],
            'critical_failure' => $critical,
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
