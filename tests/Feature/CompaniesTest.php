<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_companies(): void
    {
        $this->get('/companies')->assertRedirect('/');
    }

    public function test_authenticated_admin_can_access_companies_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Companies/Index', false));
    }

    public function test_admin_can_create_a_company(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/companies', [
                'name' => 'Acme Sales',
                'website' => 'https://example.com',
                'email' => 'hello@example.com',
            ])
            ->assertRedirect('/companies');

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Sales',
            'email' => 'hello@example.com',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_a_company(): void
    {
        $user = User::factory()->create();
        $company = \App\Models\Company::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patch("/companies/{$company->id}", [
                'name' => 'New Name',
            ])
            ->assertRedirect('/companies');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'New Name',
        ]);
    }

    public function test_company_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/companies')
            ->post('/companies', [
                'name' => '',
                'website' => 'not-a-url',
                'email' => 'bad-email',
            ])
            ->assertRedirect('/companies')
            ->assertSessionHasErrors(['name', 'website', 'email']);
    }
}
