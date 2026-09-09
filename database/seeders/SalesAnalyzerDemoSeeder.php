<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class SalesAnalyzerDemoSeeder extends Seeder
{
    /**
     * Demo data for local/test use. Do not run automatically in production.
     */
    public function run(): void
    {
        $alpha = Company::query()->create([
            'name' => 'Northwind HVAC',
            'legal_name' => 'Northwind HVAC Ltd',
            'website' => 'https://example.com',
            'industry' => 'HVAC',
            'description' => 'Residential heating and cooling.',
            'country' => 'Canada',
            'city' => 'Toronto',
            'phone' => '+1-555-0100',
            'email' => 'hello@example.com',
            'is_active' => true,
        ]);

        $beta = Company::query()->create([
            'name' => 'Bright Clean Co',
            'industry' => 'Cleaning',
            'city' => 'Austin',
            'is_active' => true,
        ]);

        $anna = Employee::query()->create([
            'company_id' => $alpha->id,
            'first_name' => 'Anna',
            'last_name' => 'Lee',
            'email' => 'anna@example.com',
            'position' => 'Sales Manager',
            'is_active' => true,
        ]);

        $ben = Employee::query()->create([
            'company_id' => $alpha->id,
            'first_name' => 'Ben',
            'last_name' => 'Ortiz',
            'position' => 'Closer',
            'is_active' => true,
        ]);

        $cara = Employee::query()->create([
            'company_id' => $beta->id,
            'first_name' => 'Cara',
            'last_name' => 'Nguyen',
            'position' => 'Account Executive',
            'is_active' => false,
        ]);

        foreach ([
            ['employee' => $anna, 'status' => 'completed', 'duration' => 420],
            ['employee' => $anna, 'status' => 'processing', 'duration' => 180],
            ['employee' => $ben, 'status' => 'failed', 'duration' => 90],
            ['employee' => $ben, 'status' => 'pending', 'duration' => null],
            ['employee' => $cara, 'status' => 'uploaded', 'duration' => 300],
        ] as $row) {
            Call::query()->create([
                'company_id' => $row['employee']->company_id,
                'employee_id' => $row['employee']->id,
                'source' => 'manual',
                'original_filename' => 'demo-'.$row['status'].'.mp3',
                'duration_seconds' => $row['duration'],
                'status' => $row['status'],
                'recorded_at' => now()->subDays(random_int(1, 10)),
            ]);
        }
    }
}
