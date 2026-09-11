<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale_from_home(): void
    {
        $this->from('/')
            ->post(route('locale.update'), ['locale' => 'ru'])
            ->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home', false)
                ->where('locale', 'ru'));
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->from('/')
            ->post(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_authenticated_user_can_switch_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('locale.update'), ['locale' => 'uk'])
            ->assertRedirect('/dashboard');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'uk'));
    }

    public function test_legacy_settings_language_route_still_updates_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings')
            ->post(route('settings.language.update'), ['locale' => 'ru'])
            ->assertRedirect('/settings');

        $this->assertSame('ru', session('locale'));
    }
}
