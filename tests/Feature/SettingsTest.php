<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['handling_fee_discount' => 0]);
    }

    public function test_can_get_settings(): void
    {
        $this->user->update(['handling_fee_discount' => 0.4]);

        $this->actingAs($this->user)->getJson('/api/settings')
            ->assertOk()
            ->assertJson(['handling_fee_discount' => '0.4000']);
    }

    public function test_can_update_handling_fee_discount(): void
    {
        $this->actingAs($this->user)->patchJson('/api/settings', [
            'handling_fee_discount' => 0.4,
        ])->assertOk()->assertJson(['handling_fee_discount' => '0.4000']);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'handling_fee_discount' => 0.4,
        ]);
    }

    public function test_discount_must_be_between_0_and_1(): void
    {
        $this->actingAs($this->user)->patchJson('/api/settings', ['handling_fee_discount' => 1.5])
            ->assertUnprocessable()->assertJsonValidationErrors(['handling_fee_discount']);

        $this->actingAs($this->user)->patchJson('/api/settings', ['handling_fee_discount' => -0.1])
            ->assertUnprocessable()->assertJsonValidationErrors(['handling_fee_discount']);
    }

    public function test_settings_require_auth(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->patchJson('/api/settings', ['handling_fee_discount' => 0.4])->assertUnauthorized();
    }
}
