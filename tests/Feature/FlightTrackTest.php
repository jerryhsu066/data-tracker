<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FlightTrackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Flight $flight;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->flight = Flight::factory()->create(['user_id' => $this->user->id]);
    }

    private function validGpx(int $pointCount = 3): string
    {
        $trkpts = '';
        for ($i = 0; $i < $pointCount; $i++) {
            $lat = 25.0 + $i * 0.5;
            $lon = 121.0 + $i * 0.5;
            $trkpts .= "      <trkpt lat=\"{$lat}\" lon=\"{$lon}\"><ele>10000</ele></trkpt>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1">
  <trk>
    <trkseg>
{$trkpts}    </trkseg>
  </trk>
</gpx>
XML;
    }

    private function validKml(int $pointCount = 3): string
    {
        $coords = '';
        for ($i = 0; $i < $pointCount; $i++) {
            $lat = 25.0 + $i * 0.5;
            $lon = 121.0 + $i * 0.5;
            $coords .= "          {$lon},{$lat},10000\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <LineString>
        <coordinates>
{$coords}        </coordinates>
      </LineString>
    </Placemark>
  </Document>
</kml>
XML;
    }

    private function makeUploadedFile(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'track');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/xml', null, true);
    }

    public function test_can_upload_gpx_track(): void
    {
        $file = $this->makeUploadedFile($this->validGpx(), 'flight.gpx');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['track_points']);

        $this->flight->refresh();
        $this->assertIsArray($this->flight->track_points);
        $this->assertGreaterThanOrEqual(2, count($this->flight->track_points));
    }

    public function test_can_upload_kml_track(): void
    {
        $file = $this->makeUploadedFile($this->validKml(), 'flight.kml');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['track_points']);

        $this->flight->refresh();
        $this->assertIsArray($this->flight->track_points);
        $this->assertGreaterThanOrEqual(2, count($this->flight->track_points));
    }

    public function test_upload_rejects_invalid_file(): void
    {
        $file = $this->makeUploadedFile('just some text', 'flight.txt');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertUnprocessable();
    }

    public function test_upload_rejects_empty_track(): void
    {
        $emptyGpx = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1">
  <trk>
    <trkseg>
    </trkseg>
  </trk>
</gpx>
XML;
        $file = $this->makeUploadedFile($emptyGpx, 'empty.gpx');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertUnprocessable();
    }

    public function test_can_delete_track(): void
    {
        $this->flight->update(['track_points' => [[25.0, 121.0], [26.0, 122.0]]]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/flights/{$this->flight->id}/track");

        $response->assertOk();

        $this->flight->refresh();
        $this->assertNull($this->flight->track_points);
    }

    public function test_cannot_upload_track_to_other_users_flight(): void
    {
        $otherUser = User::factory()->create();
        $file = $this->makeUploadedFile($this->validGpx(), 'flight.gpx');

        $response = $this->actingAs($otherUser)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertForbidden();
    }

    public function test_can_preview_gpx_track(): void
    {
        $file = $this->makeUploadedFile($this->validGpx(), 'flight.gpx');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track/preview", ['track' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['points']);

        $this->assertIsArray($response->json('points'));
        $this->assertGreaterThanOrEqual(2, count($response->json('points')));

        // Preview should NOT save to the flight
        $this->flight->refresh();
        $this->assertNull($this->flight->track_points);
    }

    public function test_upload_simplifies_track(): void
    {
        // Generate a GPX with many points — should be simplified
        $file = $this->makeUploadedFile($this->validGpx(500), 'long.gpx');

        $response = $this->actingAs($this->user)
            ->postJson("/api/flights/{$this->flight->id}/track", ['track' => $file]);

        $response->assertOk();

        $this->flight->refresh();
        $this->assertIsArray($this->flight->track_points);
        $this->assertLessThan(500, count($this->flight->track_points));
        $this->assertGreaterThanOrEqual(2, count($this->flight->track_points));
    }
}
