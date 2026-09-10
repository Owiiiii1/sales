<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyScorecard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyScorecard>
 */
class CompanyScorecardFactory extends Factory
{
    protected $model = CompanyScorecard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Inbound estimate scorecard',
            'description' => 'First-call quality.',
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
