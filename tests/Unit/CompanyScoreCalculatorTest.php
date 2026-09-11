<?php

namespace Tests\Unit;

use App\Services\Analysis\CompanyScoreCalculator;
use Tests\TestCase;

class CompanyScoreCalculatorTest extends TestCase
{
    public function test_weighted_score_is_calculated_application_side(): void
    {
        $snapshot = [
            'criteria' => [
                ['key' => 'system_age', 'weight' => 40, 'max_score' => 100],
                ['key' => 'estimate', 'weight' => 60, 'max_score' => 100],
            ],
        ];

        $total = app(CompanyScoreCalculator::class)->total([
            ['key' => 'system_age', 'score' => 80, 'applicable' => true],
            ['key' => 'estimate', 'score' => 50, 'applicable' => true],
        ], $snapshot);

        $this->assertSame(62, $total);
    }

    public function test_non_applicable_criteria_are_excluded(): void
    {
        $snapshot = [
            'criteria' => [
                ['key' => 'system_age', 'weight' => 40, 'max_score' => 100],
                ['key' => 'estimate', 'weight' => 60, 'max_score' => 100],
            ],
        ];

        $total = app(CompanyScoreCalculator::class)->total([
            ['key' => 'system_age', 'score' => 80, 'applicable' => true],
            ['key' => 'estimate', 'score' => 10, 'applicable' => false],
        ], $snapshot);

        $this->assertSame(80, $total);
    }

    public function test_empty_applicable_weights_return_null(): void
    {
        $this->assertNull(app(CompanyScoreCalculator::class)->total([], ['criteria' => [
            ['key' => 'system_age', 'weight' => 40, 'max_score' => 100],
        ]]));
    }

    public function test_no_trigger_leaves_weighted_score_unchanged(): void
    {
        $snapshot = $this->snapshotWithCap();

        $scored = app(CompanyScoreCalculator::class)->scored([
            ['key' => 'prep_accuracy', 'score' => 90, 'applicable' => true, 'critical_failure' => false],
            ['key' => 'estimate', 'score' => 90, 'applicable' => true, 'critical_failure' => false],
        ], $snapshot);

        $this->assertSame(90, $scored['weighted_score']);
        $this->assertSame(90, $scored['total_score']);
        $this->assertSame([], $scored['triggered_caps']);
    }

    public function test_one_cap_lowers_final_score(): void
    {
        $snapshot = $this->snapshotWithCap();

        $scored = app(CompanyScoreCalculator::class)->scored([
            ['key' => 'prep_accuracy', 'score' => 92, 'applicable' => true, 'critical_failure' => true],
            ['key' => 'estimate', 'score' => 92, 'applicable' => true, 'critical_failure' => false],
        ], $snapshot);

        $this->assertSame(92, $scored['weighted_score']);
        $this->assertSame(70, $scored['total_score']);
        $this->assertCount(1, $scored['triggered_caps']);
        $this->assertSame('Critical factual error', $scored['triggered_caps'][0]['name']);
    }

    public function test_multiple_caps_use_the_lowest_max(): void
    {
        $snapshot = $this->snapshotWithCap();
        $snapshot['caps'][] = [
            'id' => 2,
            'name' => 'Forbidden claim',
            'criterion_key' => null,
            'trigger_type' => 'forbidden_claim_violation',
            'max_total_score' => 40,
            'description' => null,
        ];

        $scored = app(CompanyScoreCalculator::class)->scored(
            [
                ['key' => 'prep_accuracy', 'score' => 92, 'applicable' => true, 'critical_failure' => true],
                ['key' => 'estimate', 'score' => 92, 'applicable' => true, 'critical_failure' => false],
            ],
            $snapshot,
            ['forbidden_claims' => ['violations' => [['text' => 'Lifetime warranty']]]],
        );

        $this->assertSame(92, $scored['weighted_score']);
        $this->assertSame(40, $scored['total_score']);
        $this->assertCount(2, $scored['triggered_caps']);
    }

    public function test_forbidden_claim_cap_requires_confirmed_violation(): void
    {
        $snapshot = [
            'criteria' => [
                ['key' => 'prep_accuracy', 'weight' => 100, 'max_score' => 100],
            ],
            'caps' => [[
                'id' => 9,
                'name' => 'Forbidden claim',
                'criterion_key' => null,
                'trigger_type' => 'forbidden_claim_violation',
                'max_total_score' => 50,
                'description' => null,
            ]],
        ];

        $without = app(CompanyScoreCalculator::class)->scored(
            [['key' => 'prep_accuracy', 'score' => 88, 'applicable' => true, 'critical_failure' => false]],
            $snapshot,
            ['forbidden_claims' => ['violations' => []]],
        );
        $this->assertSame(88, $without['total_score']);
        $this->assertSame([], $without['triggered_caps']);

        $with = app(CompanyScoreCalculator::class)->scored(
            [['key' => 'prep_accuracy', 'score' => 88, 'applicable' => true, 'critical_failure' => false]],
            $snapshot,
            ['forbidden_claims' => ['violations' => [['text' => 'Guaranteed 100% occupancy']]]],
        );
        $this->assertSame(88, $with['weighted_score']);
        $this->assertSame(50, $with['total_score']);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotWithCap(): array
    {
        return [
            'criteria' => [
                ['key' => 'prep_accuracy', 'weight' => 40, 'max_score' => 100],
                ['key' => 'estimate', 'weight' => 60, 'max_score' => 100],
            ],
            'caps' => [[
                'id' => 1,
                'name' => 'Critical factual error',
                'criterion_key' => 'prep_accuracy',
                'trigger_type' => 'criterion_critical_failure',
                'max_total_score' => 70,
                'description' => 'Outdated event date',
            ]],
        ];
    }
}
