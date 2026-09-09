<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CallsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_calls(): void
    {
        $this->get('/calls')->assertRedirect('/');
    }

    public function test_authenticated_admin_can_access_calls_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/calls')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Calls/Index', false));
    }

    public function test_admin_can_create_a_call_for_a_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->post('/calls', [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'source' => 'manual',
                'original_filename' => 'demo.mp3',
                'duration_seconds' => 120,
                'status' => 'pending',
            ])
            ->assertRedirect('/calls');

        $this->assertDatabaseHas('calls', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_call_requires_a_company(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/calls')
            ->post('/calls', [
                'status' => 'pending',
            ])
            ->assertRedirect('/calls')
            ->assertSessionHasErrors(['company_id']);
    }

    public function test_employee_from_another_company_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $foreignEmployee = Employee::factory()->create(['company_id' => $other->id]);

        $this->actingAs($user)
            ->from('/calls')
            ->post('/calls', [
                'company_id' => $company->id,
                'employee_id' => $foreignEmployee->id,
                'status' => 'pending',
            ])
            ->assertRedirect('/calls')
            ->assertSessionHasErrors(['employee_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->from('/calls')
            ->post('/calls', [
                'company_id' => $company->id,
                'status' => 'transcribing',
                'duration_seconds' => -5,
            ])
            ->assertRedirect('/calls')
            ->assertSessionHasErrors(['status', 'duration_seconds']);
    }

    public function test_call_factory_can_bind_employee_to_same_company(): void
    {
        $employee = Employee::factory()->create();
        $call = Call::factory()->forEmployee($employee)->create(['status' => 'completed']);

        $this->assertSame($employee->company_id, $call->company_id);
        $this->assertSame($employee->id, $call->employee_id);
    }
}
