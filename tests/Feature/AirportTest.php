<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\FlightSetting;
use App\Models\User;
use App\Services\AirportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_airports(): void
    {
        Airport::create([
            'iata' => 'TPE', 'name' => 'Taoyuan International', 'city' => 'Taipei',
            'country' => 'Taiwan', 'lat' => 25.0777, 'lng' => 121.2325, 'tz' => 'Asia/Taipei',
        ]);
        Airport::create([
            'iata' => 'HND', 'name' => 'Haneda', 'city' => 'Tokyo',
            'country' => 'Japan', 'lat' => 35.5494, 'lng' => 139.7798, 'tz' => 'Asia/Tokyo',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/airports');

        $response->assertOk()->assertJsonCount(2);
        $response->assertJsonFragment(['iata' => 'HND']);
        $response->assertJsonFragment(['iata' => 'TPE']);
    }

    public function test_can_search_airports(): void
    {
        Airport::create([
            'iata' => 'TPE', 'name' => 'Taoyuan International', 'city' => 'Taipei',
            'country' => 'Taiwan', 'lat' => 25.0777, 'lng' => 121.2325, 'tz' => 'Asia/Taipei',
        ]);
        Airport::create([
            'iata' => 'HND', 'name' => 'Haneda', 'city' => 'Tokyo',
            'country' => 'Japan', 'lat' => 35.5494, 'lng' => 139.7798, 'tz' => 'Asia/Tokyo',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/airports?search=tok');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['iata' => 'HND']);
    }

    public function test_can_show_airport(): void
    {
        Airport::create([
            'iata' => 'TPE', 'name' => 'Taoyuan International', 'city' => 'Taipei',
            'country' => 'Taiwan', 'lat' => 25.0777, 'lng' => 121.2325, 'tz' => 'Asia/Taipei',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/airports/TPE');

        $response->assertOk()
                 ->assertJsonFragment(['iata' => 'TPE', 'tz' => 'Asia/Taipei']);
    }

    public function test_show_returns_404_for_unknown_airport_without_api_key(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/flights/airports/GAJ');

        $response->assertNotFound();
    }

    public function test_show_fetches_unknown_airport_from_aviationstack(): void
    {
        FlightSetting::create([
            'user_id'           => $this->user->id,
            'aviationstack_key' => 'test-key',
        ]);

        Http::fake([
            'api.aviationstack.com/*' => Http::response([
                'data' => [
                    [
                        'airport_name' => 'Yamagata',
                        'iata_code'    => 'GAJ',
                        'city_iata_code' => 'GAJ',
                        'country_name' => 'Japan',
                        'latitude'     => '38.4119',
                        'longitude'    => '140.3717',
                        'timezone'     => 'Asia/Tokyo',
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/airports/GAJ');

        $response->assertOk()
                 ->assertJsonFragment(['iata' => 'GAJ', 'tz' => 'Asia/Tokyo']);

        // Verify it was persisted
        $this->assertDatabaseHas('airports', ['iata' => 'GAJ', 'tz' => 'Asia/Tokyo']);
    }

    public function test_airport_service_returns_timezone_from_db(): void
    {
        Airport::create([
            'iata' => 'HND', 'name' => 'Haneda', 'city' => 'Tokyo',
            'country' => 'Japan', 'lat' => 35.5494, 'lng' => 139.7798, 'tz' => 'Asia/Tokyo',
        ]);

        $service = new AirportService();
        $this->assertEquals('Asia/Tokyo', $service->timezone('HND'));
    }

    public function test_airport_service_estimates_timezone_from_longitude(): void
    {
        // Airport with coordinates but no tz
        Airport::create([
            'iata' => 'XXX', 'name' => 'Test', 'city' => 'Test',
            'country' => 'Japan', 'lat' => 38.0, 'lng' => 140.0, 'tz' => null,
        ]);

        $service = new AirportService();
        $tz = $service->timezone('XXX');
        // lng 140 / 15 = 9.33 → round to 9 → Asia/Tokyo
        $this->assertEquals('Asia/Tokyo', $tz);
    }

    public function test_airport_service_falls_back_to_app_timezone(): void
    {
        $service = new AirportService();
        $tz = $service->timezone('UNKNOWN');
        $this->assertEquals(config('app.timezone'), $tz);
    }

    public function test_airport_service_resolves_flight_timezones(): void
    {
        Airport::create([
            'iata' => 'TSA', 'name' => 'Songshan', 'city' => 'Taipei',
            'country' => 'Taiwan', 'lat' => 25.0694, 'lng' => 121.5525, 'tz' => 'Asia/Taipei',
        ]);
        Airport::create([
            'iata' => 'HND', 'name' => 'Haneda', 'city' => 'Tokyo',
            'country' => 'Japan', 'lat' => 35.5494, 'lng' => 139.7798, 'tz' => 'Asia/Tokyo',
        ]);

        $service = new AirportService();
        [$depTz, $arrTz] = $service->resolveTimezones('TSA', 'HND');

        $this->assertEquals('Asia/Taipei', $depTz);
        $this->assertEquals('Asia/Tokyo', $arrTz);
    }
}
