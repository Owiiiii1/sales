<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_employees(): void
    {
        $this->get('/employees')->assertRedirect('/');
    }

    public function test_authenticated_admin_can_access_employees_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/employees')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Employees/Index', false));
    }

    public function test_admin_can_create_an_employee_for_a_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/employees', [
                'company_id' => $company->id,
                'first_name' => 'Anna',
                'last_name' => 'Lee',
                'email' => 'anna@example.com',
            ])
            ->assertRedirect('/employees');

        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'first_name' => 'Anna',
            'email' => 'anna@example.com',
        ]);
    }

    public function test_company_is_required_to_create_an_employee(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/employees')
            ->post('/employees', [
                'first_name' => 'Anna',
            ])
            ->assertRedirect('/employees')
            ->assertSessionHasErrors(['company_id']);
    }

    public function test_admin_can_update_an_employee(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['first_name' => 'Old']);

        $this->actingAs($user)
            ->patch("/employees/{$employee->id}", [
                'company_id' => $employee->company_id,
                'first_name' => 'Updated',
            ])
            ->assertRedirect('/employees');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Updated',
        ]);
    }
}
