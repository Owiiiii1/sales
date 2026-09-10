<?php

namespace Database\Factories;

use App\Models\CompanyScorecard;
use App\Models\CompanyScorecardCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyScorecardCriterion>
 */
class CompanyScorecardCriterionFactory extends Factory
{
    protected $model = CompanyScorecardCriterion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scorecard_id' => CompanyScorecard::factory(),
            'key' => 'system_age',
            'name' => 'Asked system age',
            'description' => 'Seller asked how old the current system is.',
            'weight' => 40,
            'max_score' => 100,
            'is_critical' => false,
            'ai_instructions' => 'Score 100 only if the seller asked the age of the current HVAC system.',
            'sequence' => 1,
            'is_active' => true,
        ];
    }
}
