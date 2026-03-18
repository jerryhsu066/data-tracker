<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FlightImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $csvHeader = "flight_date,airline,flight_number,departure_airport,arrival_airport,departure_time,arrival_time,aircraft_type,seat_class,seat_number,booking_reference,ticket_price,tail_number,notes\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Export ─────────────────────────────────────────────────────────────────

    public function test_can_export_flights_as_csv(): void
    {
        Flight::factory()->create([
            'user_id' => $this->user->id,
            'flight_date' => '2026-03-15',
            'airline' => 'CI',
            'flight_number' => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport' => 'NRT',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/export?format=csv');

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="flights.csv"');
    }

    public function test_can_export_flights_as_json(): void
    {
        Flight::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/flights/export?format=json');

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="flights.json"');
    }

    public function test_export_returns_example_when_no_flights(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/flights/export?format=json');

        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['flight_number' => 'CI123']);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function test_can_preview_csv_import(): void
    {
        $csv = $this->csvHeader
             . "2026-03-15,China Airlines,CI123,TPE,NRT,,,A330-300,economy,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('flights.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/preview', [
            'file' => $file, 'format' => 'csv',
        ]);

        $response->assertOk()->assertJsonFragment(['valid' => 1, 'total' => 1]);
    }

    public function test_preview_detects_duplicates(): void
    {
        Flight::factory()->create([
            'user_id' => $this->user->id,
            'flight_date' => '2026-03-15',
            'flight_number' => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport' => 'NRT',
        ]);

        $csv = $this->csvHeader
             . "2026-03-15,China Airlines,CI123,TPE,NRT,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('flights.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/preview', [
            'file' => $file, 'format' => 'csv',
        ]);

        $response->assertOk()->assertJsonPath('duplicates.0.label', 'CI123 TPE→NRT 2026-03-15');
    }

    public function test_preview_detects_invalid_rows(): void
    {
        $csv = $this->csvHeader
             . "2026-03-15,,CI123,TPE,NRT,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('flights.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import/preview', [
            'file' => $file, 'format' => 'csv',
        ]);

        $response->assertOk()->assertJsonPath('invalid.0.reason', 'Missing required fields (flight_date, airline, flight_number, departure_airport, arrival_airport)');
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function test_can_import_flights_from_csv(): void
    {
        $csv = $this->csvHeader
             . "2026-03-15,China Airlines,CI123,TPE,NRT,2026-03-15 08:30:00,,A330-300,economy,,,,,\n"
             . "2026-03-20,EVA Air,BR189,TPE,LAX,,,B787-9,business,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('flights.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import', [
            'file' => $file, 'format' => 'csv',
        ]);

        $response->assertOk()->assertJsonFragment(['imported' => 2]);
        $this->assertDatabaseHas('flights', ['flight_number' => 'CI123', 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('flights', ['flight_number' => 'BR189', 'user_id' => $this->user->id]);
    }

    public function test_import_skips_duplicates_by_default(): void
    {
        Flight::factory()->create([
            'user_id' => $this->user->id,
            'flight_date' => '2026-03-15',
            'flight_number' => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport' => 'NRT',
        ]);

        $csv = $this->csvHeader
             . "2026-03-15,China Airlines,CI123,TPE,NRT,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('flights.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import', [
            'file' => $file, 'format' => 'csv', 'skip_duplicates' => true,
        ]);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
    }

    public function test_can_import_flights_from_json(): void
    {
        $json = json_encode([
            [
                'flight_date' => '2026-03-15',
                'airline' => 'China Airlines',
                'flight_number' => 'CI123',
                'departure_airport' => 'TPE',
                'arrival_airport' => 'NRT',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('flights.json', $json);

        $response = $this->actingAs($this->user)->postJson('/api/flights/import', [
            'file' => $file, 'format' => 'json',
        ]);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
    }
}
