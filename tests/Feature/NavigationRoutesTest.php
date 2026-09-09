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
        $this->get('/dashboard')->assertRedirect('/');
        $this->get('/companies')->assertRedirect('/');
        $this->get('/employees')->assertRedirect('/');
        $this->get('/calls')->assertRedirect('/');
        $this->get('/settings')->assertRedirect('/');
    }

    public function test_authenticated_user_can_open_product_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->has('stats')
                ->has('recentCalls'));

        $this->actingAs($user)->get('/companies')->assertOk();
        $this->actingAs($user)->get('/employees')->assertOk();
        $this->actingAs($user)->get('/calls')->assertOk();
        $this->actingAs($user)->get('/settings')->assertOk();
    }

    public function test_legacy_kit_routes_remain_available_but_are_not_required_in_nav(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/customers')->assertOk();
        $this->actingAs($user)->get('/orders')->assertOk();
    }
}
