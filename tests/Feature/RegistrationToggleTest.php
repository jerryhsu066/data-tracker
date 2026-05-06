<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationToggleTest extends TestCase
{
    use RefreshDatabase;

    // ── First-user / admin bootstrapping ─────────────────────────────────────

    public function test_first_user_to_register_becomes_admin(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertTrue(User::first()->is_admin);
    }

    public function test_subsequent_users_are_not_admin(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Regular',
            'email'                 => 'user@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertFalse(User::where('email', 'user@example.com')->first()->is_admin);
    }

    // ── Registration gate ─────────────────────────────────────────────────────

    public function test_registration_allowed_by_default(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->postJson('/api/auth/register', [
            'name'                  => 'New User',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_registration_blocked_when_disabled(): void
    {
        User::factory()->create(['is_admin' => true]);
        AppSetting::get()->update(['registration_enabled' => false]);

        $this->postJson('/api/auth/register', [
            'name'                  => 'New User',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_first_user_can_always_register_even_when_disabled(): void
    {
        AppSetting::get()->update(['registration_enabled' => false]);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertTrue(User::first()->is_admin);
    }

    // ── Admin settings API ────────────────────────────────────────────────────

    public function test_registration_enabled_is_true_by_default(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJson(['registration_enabled' => true]);
    }

    public function test_admin_can_disable_registration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patchJson('/api/admin/settings', ['registration_enabled' => false])
            ->assertOk()
            ->assertJson(['registration_enabled' => false]);

        $this->assertFalse(AppSetting::get()->registration_enabled);
    }

    public function test_admin_can_re_enable_registration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AppSetting::get()->update(['registration_enabled' => false]);

        $this->actingAs($admin)->patchJson('/api/admin/settings', ['registration_enabled' => true])
            ->assertOk()
            ->assertJson(['registration_enabled' => true]);
    }

    public function test_non_admin_cannot_read_admin_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_non_admin_cannot_update_admin_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson('/api/admin/settings', ['registration_enabled' => false])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_settings(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->patchJson('/api/admin/settings', ['registration_enabled' => false])->assertUnauthorized();
    }
}
