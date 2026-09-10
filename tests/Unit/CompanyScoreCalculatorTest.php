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
}
