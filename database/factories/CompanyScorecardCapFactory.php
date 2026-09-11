<?php

namespace Database\Factories;

use App\Models\CompanyScorecard;
use App\Models\CompanyScorecardCap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyScorecardCap>
 */
class CompanyScorecardCapFactory extends Factory
{
    protected $model = CompanyScorecardCap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scorecard_id' => CompanyScorecard::factory(),
            'name' => 'Critical factual error',
            'criterion_key' => 'prep_accuracy',
            'trigger_type' => CompanyScorecardCap::TRIGGER_CRITERION_CRITICAL_FAILURE,
            'max_total_score' => 70,
            'description' => 'Outdated event date presented as current.',
            'is_active' => true,
            'sequence' => 1,
        ];
    }
}
