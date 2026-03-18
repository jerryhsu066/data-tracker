<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlightApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_flights(): void
    {
        $this->getJson('/api/flights')->assertUnauthorized();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_can_list_flights(): void
    {
        Flight::factory()->count(3)->create(['user_id' => $this->user->id, 'flight_date' => '2026-03-15']);

        $response = $this->actingAs($this->user)->getJson('/api/flights');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_can_filter_flights_by_year(): void
    {
        Flight::factory()->create(['user_id' => $this->user->id, 'flight_date' => '2026-03-15']);
        Flight::factory()->create(['user_id' => $this->user->id, 'flight_date' => '2025-06-10']);

        $response = $this->actingAs($this->user)->getJson('/api/flights?year=2026');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_only_sees_own_flights(): void
    {
        $other = User::factory()->create();
        Flight::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->getJson('/api/flights');

        $response->assertOk()->assertJsonCount(0);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_can_create_flight(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-03-15',
            'airline'           => 'China Airlines',
            'flight_number'     => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport'   => 'NRT',
        ]);

        $response->assertCreated()
                 ->assertJsonStructure(['id', 'flight_date', 'airline', 'flight_number', 'departure_airport', 'arrival_airport'])
                 ->assertJsonFragment(['airline' => 'China Airlines', 'flight_number' => 'CI123']);

        $this->assertDatabaseHas('flights', ['user_id' => $this->user->id, 'flight_number' => 'CI123']);
    }

    public function test_can_create_flight_with_all_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-03-15',
            'airline'           => 'China Airlines',
            'flight_number'     => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport'   => 'NRT',
            'departure_time'    => '2026-03-15 08:30:00',
            'arrival_time'      => '2026-03-15 12:45:00',
            'aircraft_type'     => 'A330-300',
            'seat_class'        => 'economy',
            'seat_number'       => '32A',
            'booking_reference' => 'ABC123',
            'ticket_price'      => 15000.00,
            'tail_number'       => 'B-18302',
            'notes'             => 'Window seat, good view',
        ]);

        $response->assertCreated()
                 ->assertJsonFragment(['aircraft_type' => 'A330-300', 'seat_class' => 'economy']);
    }

    public function test_flight_requires_mandatory_fields(): void
    {
        $this->actingAs($this->user)->postJson('/api/flights', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['flight_date', 'airline', 'flight_number', 'departure_airport', 'arrival_airport']);
    }

    public function test_airport_codes_must_be_3_characters(): void
    {
        $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-03-15',
            'airline'           => 'Test',
            'flight_number'     => 'TS1',
            'departure_airport' => 'ABCD',
            'arrival_airport'   => 'AB',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['departure_airport', 'arrival_airport']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_can_update_flight(): void
    {
        $flight = Flight::factory()->create(['user_id' => $this->user->id, 'airline' => 'Old Airline']);

        $response = $this->actingAs($this->user)->patchJson("/api/flights/{$flight->id}", [
            'airline' => 'New Airline',
            'notes'   => 'Updated note',
        ]);

        $response->assertOk()->assertJsonFragment(['airline' => 'New Airline', 'notes' => 'Updated note']);
        $this->assertDatabaseHas('flights', ['id' => $flight->id, 'airline' => 'New Airline']);
    }

    public function test_cannot_update_another_users_flight(): void
    {
        $other  = User::factory()->create();
        $flight = Flight::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->patchJson("/api/flights/{$flight->id}", ['airline' => 'Hack'])
             ->assertForbidden();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_can_delete_flight(): void
    {
        $flight = Flight::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/flights/{$flight->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('flights', ['id' => $flight->id]);
    }

    public function test_cannot_delete_another_users_flight(): void
    {
        $other  = User::factory()->create();
        $flight = Flight::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->deleteJson("/api/flights/{$flight->id}")
             ->assertForbidden();
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function test_can_get_flight_stats(): void
    {
        Flight::factory()->create(['user_id' => $this->user->id, 'flight_date' => '2026-01-10', 'airline' => 'CI', 'departure_airport' => 'TPE', 'arrival_airport' => 'NRT', 'seat_class' => 'economy']);
        Flight::factory()->create(['user_id' => $this->user->id, 'flight_date' => '2026-03-20', 'airline' => 'CI', 'departure_airport' => 'NRT', 'arrival_airport' => 'TPE', 'seat_class' => 'business']);
        Flight::factory()->create(['user_id' => $this->user->id, 'flight_date' => '2025-12-01', 'airline' => 'BR', 'departure_airport' => 'TPE', 'arrival_airport' => 'LAX', 'seat_class' => 'economy']);

        $response = $this->actingAs($this->user)->getJson('/api/flights/stats');

        $response->assertOk()
                 ->assertJsonStructure(['total_flights', 'unique_airports', 'unique_airlines', 'most_visited_airport', 'flights_by_year', 'flights_by_class']);

        $response->assertJsonFragment(['total_flights' => 3, 'unique_airlines' => 2]);
    }

    public function test_stats_only_counts_own_flights(): void
    {
        $other = User::factory()->create();
        Flight::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/stats');

        $response->assertOk()->assertJsonFragment(['total_flights' => 0]);
    }

    // ── Airport Auto-Resolve ───────────────────────────────────────────────────

    public function test_store_resolves_unknown_airports_via_keyless_api(): void
    {
        Http::fake([
            'www.airport-data.com/*' => Http::sequence()
                ->push(['iata' => 'HND', 'name' => 'Haneda', 'location' => 'Tokyo', 'country' => 'Japan', 'lat' => '35.5494', 'lng' => '139.7798', 'tz' => 'Asia/Tokyo'])
                ->push(['iata' => 'GAJ', 'name' => 'Yamagata', 'location' => 'Yamagata', 'country' => 'Japan', 'lat' => '38.4119', 'lng' => '140.3717', 'tz' => 'Asia/Tokyo']),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-02-01',
            'airline'           => 'JAL',
            'flight_number'     => 'JL175',
            'departure_airport' => 'HND',
            'arrival_airport'   => 'GAJ',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('airports', ['iata' => 'GAJ', 'tz' => 'Asia/Tokyo']);
        $this->assertDatabaseHas('airports', ['iata' => 'HND', 'tz' => 'Asia/Tokyo']);
    }

    public function test_store_falls_back_to_aviationstack_when_keyless_fails(): void
    {
        FlightSetting::create([
            'user_id'           => $this->user->id,
            'aviationstack_key' => 'test-key',
        ]);

        Http::fake([
            'www.airport-data.com/*' => Http::response([], 500),
            'api.aviationstack.com/*' => Http::sequence()
                ->push(['data' => [['airport_name' => 'Yamagata', 'iata_code' => 'GAJ', 'city_iata_code' => 'GAJ', 'country_name' => 'Japan', 'latitude' => '38.4119', 'longitude' => '140.3717', 'timezone' => 'Asia/Tokyo']]])
                ->push(['data' => [['airport_name' => 'Haneda', 'iata_code' => 'HND', 'city_iata_code' => 'TYO', 'country_name' => 'Japan', 'latitude' => '35.5494', 'longitude' => '139.7798', 'timezone' => 'Asia/Tokyo']]]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-02-01',
            'airline'           => 'JAL',
            'flight_number'     => 'JL175',
            'departure_airport' => 'HND',
            'arrival_airport'   => 'GAJ',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('airports', ['iata' => 'GAJ', 'tz' => 'Asia/Tokyo']);
        $this->assertDatabaseHas('airports', ['iata' => 'HND', 'tz' => 'Asia/Tokyo']);
    }

    public function test_store_skips_api_for_known_airports(): void
    {
        Airport::create([
            'iata' => 'TPE', 'name' => 'Taoyuan International', 'city' => 'Taipei',
            'country' => 'Taiwan', 'lat' => 25.0777, 'lng' => 121.2325, 'tz' => 'Asia/Taipei',
        ]);
        Airport::create([
            'iata' => 'NRT', 'name' => 'Narita', 'city' => 'Tokyo',
            'country' => 'Japan', 'lat' => 35.7647, 'lng' => 140.3864, 'tz' => 'Asia/Tokyo',
        ]);

        Http::fake();

        $response = $this->actingAs($this->user)->postJson('/api/flights', [
            'flight_date'       => '2026-03-15',
            'airline'           => 'China Airlines',
            'flight_number'     => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport'   => 'NRT',
        ]);

        $response->assertCreated();

        // No HTTP calls should have been made since both airports exist
        Http::assertNothingSent();
    }
}
