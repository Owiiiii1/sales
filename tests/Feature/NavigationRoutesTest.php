<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/companies')->assertRedirect('/login');
        $this->get('/employees')->assertRedirect('/login');
        $this->get('/calls')->assertRedirect('/login');
        $this->get('/calls/create')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_public_home_and_login_are_available_to_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Home', false));

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login', false));
    }

    public function test_authenticated_user_can_open_product_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->has('filters')
                ->has('analytics')
                ->has('analytics.kpis')
                ->has('analytics.recent_calls'));

        $this->actingAs($user)->get('/companies')->assertOk();
        $this->actingAs($user)->get('/employees')->assertOk();
        $this->actingAs($user)->get('/calls')->assertOk();
        $this->actingAs($user)->get('/calls/create')->assertOk();
        $this->actingAs($user)->get('/settings')->assertOk();
        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
    }

    public function test_legacy_kit_routes_remain_available_but_are_not_required_in_nav(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/customers')->assertOk();
        $this->actingAs($user)->get('/orders')->assertOk();
    }
}
