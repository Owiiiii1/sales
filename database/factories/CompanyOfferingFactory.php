<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyOffering>
 */
class CompanyOfferingFactory extends Factory
{
    protected $model = CompanyOffering::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => 'service',
            'name' => 'Annual HVAC maintenance',
            'description' => 'Yearly inspection and filter replacement.',
            'target_customer' => 'Homeowners',
            'value_proposition' => 'Fewer winter breakdowns.',
            'pricing' => '99 per month',
            'differentiators' => '24-hour callback',
            'common_use_cases' => 'Pre-winter checkup',
            'is_active' => true,
        ];
    }
}
