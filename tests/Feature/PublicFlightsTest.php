<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFlightsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_admin' => true]);
        $this->other = User::factory()->create();
        AppSetting::get()->update(['public_flight_user_id' => $this->owner->id]);
    }

    public function test_public_flights_returns_configured_users_flights_without_auth(): void
    {
        Flight::factory()->count(3)->create(['user_id' => $this->owner->id]);
        Flight::factory()->count(2)->create(['user_id' => $this->other->id]);

        $response = $this->getJson('/api/public/flights');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_public_flights_filters_by_year(): void
    {
        Flight::factory()->create(['user_id' => $this->owner->id, 'flight_date' => '2024-03-15']);
        Flight::factory()->create(['user_id' => $this->owner->id, 'flight_date' => '2025-06-10']);

        $response = $this->getJson('/api/public/flights?year=2024');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_public_flights_respects_configured_user_id(): void
    {
        AppSetting::get()->update(['public_flight_user_id' => $this->other->id]);
        Flight::factory()->count(2)->create(['user_id' => $this->owner->id]);
        Flight::factory()->count(4)->create(['user_id' => $this->other->id]);

        $response = $this->getJson('/api/public/flights');

        $response->assertOk()->assertJsonCount(4);
    }

    public function test_admin_can_update_public_flight_user_id(): void
    {
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/settings', ['public_flight_user_id' => $this->other->id])
            ->assertOk()
            ->assertJsonFragment(['public_flight_user_id' => $this->other->id]);

        $this->assertEquals($this->other->id, AppSetting::get()->public_flight_user_id);
    }

    public function test_non_admin_cannot_update_public_flight_user_id(): void
    {
        $this->actingAs($this->other)
            ->patchJson('/api/admin/settings', ['public_flight_user_id' => $this->owner->id])
            ->assertForbidden();
    }

    public function test_public_airports_returns_airport_list_without_auth(): void
    {
        $this->getJson('/api/public/flights/airports')->assertOk()->assertJsonIsArray();
    }
}
