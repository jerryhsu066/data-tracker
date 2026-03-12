<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jerry Hsu',
            'email' => 'jerry@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'jerry@example.com']);
    }

    public function test_register_seeds_default_cashflow_types(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Jerry',
            'email'                 => 'jerry@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = \App\Models\User::where('email', 'jerry@example.com')->first();

        $this->assertDatabaseHas('cashflow_types', ['user_id' => $user->id, 'name' => 'Income',       'is_expense' => false]);
        $this->assertDatabaseHas('cashflow_types', ['user_id' => $user->id, 'name' => 'Credit Card',  'is_expense' => true]);
        $this->assertDatabaseHas('cashflow_types', ['user_id' => $user->id, 'name' => 'Housing',      'is_expense' => true, 'merge_subtypes' => true]);
        $this->assertDatabaseHas('cashflow_types', ['user_id' => $user->id, 'name' => 'Subscription', 'is_expense' => true, 'merge_subtypes' => true]);

        $creditCard = \App\Models\CashflowType::where('user_id', $user->id)->where('name', 'Credit Card')->first();
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $creditCard->id, 'name' => 'HSBC']);
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $creditCard->id, 'name' => 'CTBC']);

        $housing = \App\Models\CashflowType::where('user_id', $user->id)->where('name', 'Housing')->first();
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $housing->id, 'name' => 'Rent']);
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $housing->id, 'name' => 'Electricity']);
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $housing->id, 'name' => 'Water']);

        $subscription = \App\Models\CashflowType::where('user_id', $user->id)->where('name', 'Subscription')->first();
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $subscription->id, 'name' => 'Netflix']);
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $subscription->id, 'name' => 'Spotify']);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'jerry@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Jerry',
            'email' => 'jerry@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create(['email' => 'jerry@example.com', 'password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jerry@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'jerry@example.com', 'password' => bcrypt('correct')]);

        $this->postJson('/api/auth/login', [
            'email' => 'jerry@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_user_can_get_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_unauthenticated_request_to_me_is_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    // ── Profile update ────────────────────────────────────────────────────────

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)->patchJson('/api/auth/me', ['name' => 'New Name'])
             ->assertOk()
             ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_update_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->patchJson('/api/auth/me', ['email' => 'new@example.com'])
             ->assertOk()
             ->assertJsonFragment(['email' => 'new@example.com']);
    }

    public function test_cannot_update_email_to_another_users_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/auth/me', ['email' => 'taken@example.com'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $this->actingAs($user)->patchJson('/api/auth/me', [
            'current_password'      => 'oldpass123',
            'password'              => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass456', $user->fresh()->password));
    }

    public function test_update_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct123')]);

        $this->actingAs($user)->patchJson('/api/auth/me', [
            'current_password'      => 'wrong',
            'password'              => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['current_password']);
    }

    public function test_user_can_set_privacy_lock(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/auth/me', ['privacy_lock' => true])
             ->assertOk()
             ->assertJsonFragment(['privacy_lock' => true]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'privacy_lock' => true]);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/auth/me', ['name' => 'Hacker'])->assertUnauthorized();
    }

    // ── Verify password ───────────────────────────────────────────────────────

    public function test_can_verify_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)->postJson('/api/auth/verify-password', ['password' => 'secret123'])
             ->assertOk();
    }

    public function test_verify_password_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)->postJson('/api/auth/verify-password', ['password' => 'wrong'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['password']);
    }

    public function test_guest_cannot_verify_password(): void
    {
        $this->postJson('/api/auth/verify-password', ['password' => 'any'])->assertUnauthorized();
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonFragment(['message' => 'Logged out successfully.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
