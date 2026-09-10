<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyObjection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyObjection>
 */
class CompanyObjectionFactory extends Factory
{
    protected $model = CompanyObjection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'objection' => 'Too expensive',
            'recommended_response' => 'Compare the monthly plan to one emergency repair visit.',
            'notes' => null,
            'priority' => 10,
            'is_active' => true,
        ];
    }
}
