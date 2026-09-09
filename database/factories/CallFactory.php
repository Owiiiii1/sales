<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
{
    protected $model = Call::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => null,
            'source' => 'manual',
            'public_token' => (string) fake()->unique()->uuid(),
            'external_id' => null,
            'original_filename' => fake()->optional()->lexify('call-????.mp3'),
            'storage_path' => null,
            'mime_type' => null,
            'file_size' => null,
            'duration_seconds' => fake()->optional()->numberBetween(30, 1800),
            'status' => fake()->randomElement(Call::STATUSES),
            'recorded_at' => fake()->optional()->dateTimeBetween('-30 days'),
            'uploaded_by' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
        ]);
    }
}
