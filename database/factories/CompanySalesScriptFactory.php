<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySalesScript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySalesScript>
 */
class CompanySalesScriptFactory extends Factory
{
    protected $model = CompanySalesScript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Inbound estimate script',
            'description' => 'First call for homeowners.',
            'script_text' => "1. Greet and confirm the caller.\n2. Ask system age.\n3. Offer an in-home estimate.",
            'is_active' => true,
        ];
    }
}
