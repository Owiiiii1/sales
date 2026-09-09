<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->optional()->company().' LLC',
            'website' => fake()->optional()->url(),
            'industry' => fake()->optional()->randomElement(['HVAC', 'Cleaning', 'SaaS', 'Retail']),
            'description' => fake()->optional()->paragraph(),
            'country' => fake()->optional()->country(),
            'city' => fake()->optional()->city(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
