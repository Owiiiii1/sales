<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'short_description' => 'HVAC installation and maintenance.',
            'sales_context' => 'We sell maintenance contracts to homeowners.',
            'target_audience' => 'Homeowners with aging HVAC systems.',
            'ideal_customer_profile' => 'Homeowners aged 35-60 in suburban areas.',
            'value_proposition' => 'Same-week installation with a 24-hour callback guarantee.',
            'usp' => '24-hour callback guarantee.',
            'pricing_context' => 'Maintenance plans start at 99 per month.',
            'competitors' => 'Local independent HVAC shops.',
            'customer_pains' => 'Unexpected breakdowns in winter.',
            'sales_goals' => 'Book an in-home estimate.',
            'desired_next_steps' => 'Schedule an estimate visit.',
            'forbidden_claims' => 'Do not promise lifetime warranties.',
            'mandatory_questions' => 'Ask about current system age.',
            'notes' => null,
            'report_language' => 'same_as_call',
        ];
    }
}
