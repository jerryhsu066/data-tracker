<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Models\FlightSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Fr24ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    public function test_can_save_fr24_username_in_settings(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/flights/settings', [
            'fr24_username' => 'testuser123',
        ]);

        $response->assertOk()->assertJsonFragment(['fr24_username' => 'testuser123']);
    }

    public function test_can_read_fr24_username_from_settings(): void
    {
        FlightSetting::create([
            'user_id'       => $this->user->id,
            'fr24_username' => 'myprofile',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/settings');

        $response->assertOk()->assertJsonFragment(['fr24_username' => 'myprofile']);
    }

    public function test_fr24_username_is_null_when_not_set(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/flights/settings');

        $response->assertOk()->assertJsonFragment(['fr24_username' => null]);
    }

    public function test_can_clear_fr24_username(): void
    {
        FlightSetting::create([
            'user_id'       => $this->user->id,
            'fr24_username' => 'olduser',
        ]);

        $response = $this->actingAs($this->user)->patchJson('/api/flights/settings', [
            'fr24_username' => null,
        ]);

        $response->assertOk()->assertJsonFragment(['fr24_username' => null]);
    }

    // ── Import ───────────────────────────────────────────────────────────────

    public function test_import_requires_configured_username(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/flights/import/fr24');

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'FR24 username is not configured. Please set it in settings first.']);
    }

    public function test_import_creates_flights_with_correct_fields(): void
    {
        FlightSetting::create([
            'user_id'       => $this->user->id,
            'fr24_username' => 'testuser',
        ]);

        Http::fake([
            'my.flightradar24.com/testuser/flights' => Http::response($this->sampleHtmlPage()),
            'my.flightradar24.com/public-scripts/flight-list/*' => Http::response([]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/fr24');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('imported'));

        $flight = Flight::where('user_id', $this->user->id)->first();
        $this->assertNotNull($flight);
        $this->assertEquals('fr24', $flight->import_source);
        $this->assertEquals('Imported from FR24', $flight->notes);
        $this->assertEquals('2026-01-15', $flight->flight_date->format('Y-m-d'));
        $this->assertEquals('CI123', $flight->flight_number);
        $this->assertEquals('TPE', $flight->departure_airport);
        $this->assertEquals('NRT', $flight->arrival_airport);
    }

    public function test_import_skips_duplicates(): void
    {
        FlightSetting::create([
            'user_id'       => $this->user->id,
            'fr24_username' => 'testuser',
        ]);

        Flight::factory()->create([
            'user_id'           => $this->user->id,
            'flight_date'       => '2026-01-15',
            'flight_number'     => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport'   => 'NRT',
        ]);

        Http::fake([
            'my.flightradar24.com/testuser/flights' => Http::response($this->sampleHtmlPage()),
            'my.flightradar24.com/public-scripts/flight-list/*' => Http::response([]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/fr24');

        $response->assertOk();
        $this->assertEquals(0, $response->json('imported'));
        $this->assertGreaterThanOrEqual(1, $response->json('skipped'));
    }

    public function test_import_handles_json_pagination(): void
    {
        FlightSetting::create([
            'user_id'       => $this->user->id,
            'fr24_username' => 'testuser',
        ]);

        Http::fake([
            'my.flightradar24.com/testuser/flights' => Http::response($this->sampleHtmlPage()),
            'my.flightradar24.com/public-scripts/flight-list/testuser/1/0/0' => Http::response([
                '1' => [
                    "<span class='inner-date'>2026-02-20</span><span class='inner-actions'><a href='/edit-flight/abc'>Edit</a></span>",
                    'BR456',
                    '<a href="https://my.flightradar24.com/airport/taipei-taoyuan-rctp" class="show-hovercard" data-hovercard-content="Taipei / Taoyuan">TPE</a>',
                    '<a href="https://my.flightradar24.com/airport/los-angeles-klax" class="show-hovercard" data-hovercard-content="Los Angeles">LAX</a>',
                    '10000', '23:30', '18:00',
                    '<a href="https://my.flightradar24.com/airline/eva-air-eva" class="show-hovercard" data-hovercard-content="EVA Air">EVA</a>',
                    '<a href="https://my.flightradar24.com/aircraft/boeing-777-300er-b77w" class="show-hovercard" data-hovercard-content="Boeing 777-300ER">B77W</a>',
                    'B-16701',
                    ' <span class="circle-icon tooltip" data-tooltip-value="Aisle">A</span>5A',
                    '',
                    "<span class='circle-icon class-business tooltip' data-tooltip-value='Business'>B</span>",
                ],
            ]),
            'my.flightradar24.com/public-scripts/flight-list/testuser/2/0/0' => Http::response([]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/fr24');

        $response->assertOk();
        $this->assertEquals(2, $response->json('imported'));

        $this->assertDatabaseHas('flights', [
            'user_id'       => $this->user->id,
            'flight_number' => 'BR456',
            'import_source' => 'fr24',
        ]);
    }

    // ── Delete FR24 Imports ──────────────────────────────────────────────────

    public function test_delete_removes_only_fr24_imported_flights(): void
    {
        // Manual flight
        Flight::factory()->create([
            'user_id'       => $this->user->id,
            'import_source' => null,
        ]);

        // FR24 imported flights
        Flight::factory()->count(3)->create([
            'user_id'       => $this->user->id,
            'import_source' => 'fr24',
        ]);

        $response = $this->actingAs($this->user)->deleteJson('/api/flights/import/fr24');

        $response->assertOk()->assertJsonFragment(['deleted' => 3]);

        // Manual flight should remain
        $this->assertEquals(1, Flight::where('user_id', $this->user->id)->count());
    }

    public function test_delete_does_not_affect_other_users_flights(): void
    {
        $otherUser = User::factory()->create();

        Flight::factory()->create([
            'user_id'       => $otherUser->id,
            'import_source' => 'fr24',
        ]);

        Flight::factory()->create([
            'user_id'       => $this->user->id,
            'import_source' => 'fr24',
        ]);

        $response = $this->actingAs($this->user)->deleteJson('/api/flights/import/fr24');

        $response->assertOk()->assertJsonFragment(['deleted' => 1]);
        $this->assertEquals(1, Flight::where('user_id', $otherUser->id)->count());
    }

    public function test_delete_soft_deletes_flights(): void
    {
        Flight::factory()->create([
            'user_id'       => $this->user->id,
            'import_source' => 'fr24',
        ]);

        $this->actingAs($this->user)->deleteJson('/api/flights/import/fr24');

        $this->assertEquals(0, Flight::where('user_id', $this->user->id)->count());
        $this->assertEquals(1, Flight::withTrashed()->where('user_id', $this->user->id)->count());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sampleHtmlPage(): string
    {
        // Matches actual my.flightradar24.com HTML structure:
        // col 0=date, 1=flight, 2=reg, 3=from, 4=to, 5=dist, 6=dep, 7=arr,
        // 8=airline, 9=aircraft, 10=seat, 11=note, 12=icons(seat class)
        return <<<'HTML'
        <html><body>
        <table>
            <tr data-row-number="0">
                <td class="flight-date"><span class="inner-date">2026-01-15</span></td>
                <td class="flight-flight">CI123</td>
                <td class="flight-reg"></td>
                <td class="flight-from"><span class="tooltip" data-tooltip-value="Taipei / Taoyuan">TPE</span></td>
                <td class="flight-to"><span class="tooltip" data-tooltip-value="Tokyo / Narita">NRT</span></td>
                <td class="flight-distance">2,100</td>
                <td class="flight-dep-time">08:30</td>
                <td class="flight-arr-time">12:45</td>
                <td class="flight-airline"><span class="tooltip" data-tooltip-value="China Airlines">CAL</span></td>
                <td class="flight-aircraft"><span class="tooltip" data-tooltip-value="Airbus A330-300">A333</span></td>
                <td class="flight-seat"><span class="circle-icon tooltip" data-tooltip-value="Window">W</span>32A</td>
                <td class="flight-note"><span></span></td>
                <td class="flight-icons"><span class="circle-icon class-economy tooltip" data-tooltip-value="Economy">E</span></td>
            </tr>
        </table>
        </body></html>
        HTML;
    }
}
