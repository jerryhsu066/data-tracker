<?php

namespace Tests\Feature;

use App\Models\FlightSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlightLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_lookup_requires_flight_number_and_date(): void
    {
        $this->actingAs($this->user)->postJson('/api/flights/lookup', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['flight_number', 'flight_date']);
    }

    public function test_lookup_returns_flight_data_from_flightradar24(): void
    {
        Http::fake([
            'api.flightradar24.com/*' => Http::response([
                'result' => [
                    'response' => [
                        'data' => [
                            [
                                'identification' => ['number' => ['default' => 'JL96']],
                                'airline' => ['name' => 'Japan Airlines'],
                                'airport' => [
                                    'origin' => ['code' => ['iata' => 'TSA']],
                                    'destination' => ['code' => ['iata' => 'HND']],
                                ],
                                'time' => [
                                    'scheduled' => [
                                        'departure' => 1738281000,
                                        'arrival'   => 1738291200,
                                    ],
                                ],
                                'aircraft' => ['model' => ['code' => '788']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'JL96',
            'flight_date'   => '2025-01-31',
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['airline' => 'Japan Airlines'])
                 ->assertJsonFragment(['departure_airport' => 'TSA'])
                 ->assertJsonFragment(['arrival_airport' => 'HND'])
                 ->assertJsonFragment(['source' => 'flightradar24']);
    }

    public function test_lookup_uses_template_when_exact_date_not_found(): void
    {
        // FR24 returns data for a different date — service should still
        // return route info as a template for the requested date
        Http::fake([
            'api.flightradar24.com/*' => Http::response([
                'result' => [
                    'response' => [
                        'data' => [
                            [
                                'identification' => ['number' => ['default' => 'JL96']],
                                'airline' => ['name' => 'Japan Airlines'],
                                'airport' => [
                                    'origin' => ['code' => ['iata' => 'TSA']],
                                    'destination' => ['code' => ['iata' => 'HND']],
                                ],
                                'time' => [
                                    'scheduled' => [
                                        'departure' => 1742259000, // 2025-03-18
                                        'arrival'   => 1742269200,
                                    ],
                                ],
                                'aircraft' => ['model' => ['code' => '788']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'JL96',
            'flight_date'   => '2026-01-31', // different date than FR24 result
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['departure_airport' => 'TSA'])
                 ->assertJsonFragment(['arrival_airport' => 'HND'])
                 ->assertJsonFragment(['aircraft_type' => '788'])
                 ->assertJsonFragment(['tail_number' => null])
                 ->assertJsonFragment(['source' => 'flightradar24']);
    }

    public function test_lookup_fills_airline_from_other_results(): void
    {
        // First result has null airline, second has it
        Http::fake([
            'api.flightradar24.com/*' => Http::response([
                'result' => [
                    'response' => [
                        'data' => [
                            [
                                'identification' => ['number' => ['default' => 'JL96']],
                                'airline' => null,
                                'airport' => [
                                    'origin' => ['code' => ['iata' => 'TSA']],
                                    'destination' => ['code' => ['iata' => 'HND']],
                                ],
                                'time' => ['scheduled' => ['departure' => 1738281000, 'arrival' => 1738291200]],
                                'aircraft' => ['model' => ['code' => '788']],
                            ],
                            [
                                'identification' => ['number' => ['default' => 'JL96']],
                                'airline' => ['name' => 'Japan Airlines'],
                                'airport' => [
                                    'origin' => ['code' => ['iata' => 'TSA']],
                                    'destination' => ['code' => ['iata' => 'HND']],
                                ],
                                'time' => ['scheduled' => ['departure' => 1738194600, 'arrival' => 1738204800]],
                                'aircraft' => ['model' => ['code' => '788']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'JL96',
            'flight_date'   => '2025-01-31',
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['airline' => 'Japan Airlines']);
    }

    public function test_lookup_falls_back_to_aviationstack(): void
    {
        FlightSetting::create([
            'user_id'           => $this->user->id,
            'aviationstack_key' => 'test-key',
        ]);

        Http::fake([
            'api.flightradar24.com/*' => Http::response([], 403),
            'api.aviationstack.com/*' => Http::response([
                'data' => [
                    [
                        'airline'   => ['name' => 'Japan Airlines'],
                        'departure' => [
                            'iata'      => 'TSA',
                            'scheduled' => '2026-01-31T09:10:00+00:00',
                        ],
                        'arrival' => [
                            'iata'      => 'HND',
                            'scheduled' => '2026-01-31T13:00:00+00:00',
                        ],
                        'aircraft' => ['iata' => '788'],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'JL96',
            'flight_date'   => '2026-01-31',
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['airline' => 'Japan Airlines'])
                 ->assertJsonFragment(['source' => 'aviationstack']);
    }

    public function test_lookup_falls_back_to_aerodatabox(): void
    {
        FlightSetting::create([
            'user_id'         => $this->user->id,
            'aerodatabox_key' => 'test-rapid-key',
        ]);

        Http::fake([
            'api.flightradar24.com/*'      => Http::response([], 403),
            'aerodatabox.p.rapidapi.com/*' => Http::response([
                [
                    'airline' => ['name' => 'Japan Airlines'],
                    'departure' => [
                        'airport' => ['iata' => 'TSA'],
                        'scheduledTime' => ['utc' => '2026-01-31 00:10Z'],
                    ],
                    'arrival' => [
                        'airport' => ['iata' => 'HND'],
                        'scheduledTime' => ['utc' => '2026-01-31 04:00Z'],
                    ],
                    'aircraft' => ['model' => 'Boeing 787-8'],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'JL96',
            'flight_date'   => '2026-01-31',
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['airline' => 'Japan Airlines'])
                 ->assertJsonFragment(['source' => 'aerodatabox']);
    }

    public function test_lookup_returns_empty_when_all_sources_fail(): void
    {
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'XX999',
            'flight_date'   => '2025-03-15',
        ]);

        $response->assertOk()
                 ->assertJsonFragment(['airline' => null])
                 ->assertJsonFragment(['source' => null]);
    }

    public function test_lookup_skips_keyed_sources_without_api_key(): void
    {
        // No FlightSetting created — no API keys
        Http::fake([
            'api.flightradar24.com/*' => Http::response([], 403),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/lookup', [
            'flight_number' => 'CI123',
            'flight_date'   => '2025-03-15',
        ]);

        $response->assertOk()->assertJsonFragment(['source' => null]);

        Http::assertNotSent(fn($request) => str_contains($request->url(), 'aviationstack'));
        Http::assertNotSent(fn($request) => str_contains($request->url(), 'aerodatabox'));
    }
}
