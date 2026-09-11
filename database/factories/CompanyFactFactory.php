<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyFact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyFact>
 */
class CompanyFactFactory extends Factory
{
    protected $model = CompanyFact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'label' => 'Next event dates',
            'value' => '12–13 September 2026, Kyiv',
            'status' => CompanyFact::STATUS_CURRENT,
            'valid_until' => '2026-09-13',
            'source' => 'Company calendar',
            'is_active' => true,
        ];
    }
}
