<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSettingsTest extends TestCase
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

        $this->actingAs($this->user)->getJson('/api/stocks/settings')
            ->assertOk()
            ->assertJson(['handling_fee_discount' => '0.4000']);
    }

    public function test_can_update_handling_fee_discount(): void
    {
        $this->actingAs($this->user)->patchJson('/api/stocks/settings', [
            'handling_fee_discount' => 0.4,
        ])->assertOk()->assertJson(['handling_fee_discount' => '0.4000']);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'handling_fee_discount' => 0.4,
        ]);
    }

    public function test_discount_must_be_between_0_and_1(): void
    {
        $this->actingAs($this->user)->patchJson('/api/stocks/settings', ['handling_fee_discount' => 1.5])
            ->assertUnprocessable()->assertJsonValidationErrors(['handling_fee_discount']);

        $this->actingAs($this->user)->patchJson('/api/stocks/settings', ['handling_fee_discount' => -0.1])
            ->assertUnprocessable()->assertJsonValidationErrors(['handling_fee_discount']);
    }

    public function test_settings_require_auth(): void
    {
        $this->getJson('/api/stocks/settings')->assertUnauthorized();
        $this->patchJson('/api/stocks/settings', ['handling_fee_discount' => 0.4])->assertUnauthorized();
    }

    public function test_gain_is_red_defaults_to_false(): void
    {
        $this->actingAs($this->user)->getJson('/api/stocks/settings')
            ->assertOk()
            ->assertJson(['gain_is_red' => false]);
    }

    public function test_can_enable_gain_is_red(): void
    {
        $this->actingAs($this->user)->patchJson('/api/stocks/settings', ['gain_is_red' => true])
            ->assertOk()
            ->assertJson(['gain_is_red' => true]);

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'gain_is_red' => true]);
    }

    public function test_can_disable_gain_is_red(): void
    {
        $this->user->update(['gain_is_red' => true]);

        $this->actingAs($this->user)->patchJson('/api/stocks/settings', ['gain_is_red' => false])
            ->assertOk()
            ->assertJson(['gain_is_red' => false]);
    }

    public function test_can_update_gain_is_red_without_touching_discount(): void
    {
        $this->user->update(['handling_fee_discount' => 0.4]);

        $this->actingAs($this->user)->patchJson('/api/stocks/settings', ['gain_is_red' => true])
            ->assertOk()
            ->assertJson(['handling_fee_discount' => '0.4000', 'gain_is_red' => true]);
    }
}
