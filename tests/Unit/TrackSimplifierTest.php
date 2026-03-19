<?php

namespace Tests\Unit;

use App\Services\TrackSimplifierService;
use PHPUnit\Framework\TestCase;

class TrackSimplifierTest extends TestCase
{
    private TrackSimplifierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrackSimplifierService();
    }

    public function test_rdp_simplifies_straight_line(): void
    {
        // Collinear points on a straight line should reduce to just endpoints
        $points = [];
        for ($i = 0; $i <= 10; $i++) {
            $points[] = [25.0 + $i * 0.1, 121.0 + $i * 0.1];
        }

        $simplified = $this->service->simplify($points, 0.005);

        $this->assertCount(2, $simplified);
        $this->assertEquals($points[0], $simplified[0]);
        $this->assertEquals($points[10], $simplified[1]);
    }

    public function test_rdp_preserves_corners(): void
    {
        // L-shaped track: go east then north — the corner must be kept
        $points = [
            [25.0, 121.0],
            [25.0, 121.5],
            [25.0, 122.0], // corner
            [25.5, 122.0],
            [26.0, 122.0],
        ];

        $simplified = $this->service->simplify($points, 0.005);

        // Should keep at least start, corner, end
        $this->assertGreaterThanOrEqual(3, count($simplified));
        $this->assertEquals([25.0, 121.0], $simplified[0]);
        $this->assertEquals([26.0, 122.0], $simplified[count($simplified) - 1]);
        // Corner point should be preserved
        $this->assertContains([25.0, 122.0], $simplified);
    }

    public function test_parses_gpx_format(): void
    {
        $gpx = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1">
  <trk>
    <trkseg>
      <trkpt lat="25.0777" lon="121.2325"><ele>100</ele></trkpt>
      <trkpt lat="25.2000" lon="121.5000"><ele>10000</ele></trkpt>
      <trkpt lat="35.5494" lon="139.7798"><ele>50</ele></trkpt>
    </trkseg>
  </trk>
</gpx>
XML;

        $points = $this->service->parseGpx($gpx);

        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(25.0777, $points[0][0], 0.0001);
        $this->assertEqualsWithDelta(121.2325, $points[0][1], 0.0001);
        $this->assertEqualsWithDelta(35.5494, $points[2][0], 0.0001);
        $this->assertEqualsWithDelta(139.7798, $points[2][1], 0.0001);
    }

    public function test_parses_kml_format(): void
    {
        // KML uses lng,lat,alt order
        $kml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <LineString>
        <coordinates>
          121.2325,25.0777,100
          121.5000,25.2000,10000
          139.7798,35.5494,50
        </coordinates>
      </LineString>
    </Placemark>
  </Document>
</kml>
XML;

        $points = $this->service->parseKml($kml);

        $this->assertCount(3, $points);
        // Should be returned as [lat, lng]
        $this->assertEqualsWithDelta(25.0777, $points[0][0], 0.0001);
        $this->assertEqualsWithDelta(121.2325, $points[0][1], 0.0001);
        $this->assertEqualsWithDelta(35.5494, $points[2][0], 0.0001);
        $this->assertEqualsWithDelta(139.7798, $points[2][1], 0.0001);
    }

    public function test_parses_kml_gx_track_format(): void
    {
        // FlightRadar24/FlightAware KML exports use gx:Track with gx:coord (space-separated lng lat alt)
        $kml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document>
    <Placemark>
      <gx:Track>
        <when>2026-03-15T08:00:00Z</when>
        <gx:coord>121.2325 25.0777 100</gx:coord>
        <when>2026-03-15T08:30:00Z</when>
        <gx:coord>121.5000 25.2000 10000</gx:coord>
        <when>2026-03-15T10:00:00Z</when>
        <gx:coord>139.7798 35.5494 50</gx:coord>
      </gx:Track>
    </Placemark>
  </Document>
</kml>
XML;

        $points = $this->service->parseKml($kml);

        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(25.0777, $points[0][0], 0.0001);
        $this->assertEqualsWithDelta(121.2325, $points[0][1], 0.0001);
        $this->assertEqualsWithDelta(35.5494, $points[2][0], 0.0001);
        $this->assertEqualsWithDelta(139.7798, $points[2][1], 0.0001);
    }

    public function test_kml_prefers_gx_track_over_linestring(): void
    {
        // KML with both a 2-point LineString and a detailed gx:Track — should use gx:Track
        $kml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document>
    <Placemark>
      <LineString>
        <coordinates>121.2325,25.0777,0 139.7798,35.5494,0</coordinates>
      </LineString>
    </Placemark>
    <Placemark>
      <gx:Track>
        <when>2026-03-15T08:00:00Z</when>
        <gx:coord>121.2325 25.0777 100</gx:coord>
        <when>2026-03-15T08:15:00Z</when>
        <gx:coord>125.0000 28.0000 10000</gx:coord>
        <when>2026-03-15T08:30:00Z</when>
        <gx:coord>130.0000 31.0000 11000</gx:coord>
        <when>2026-03-15T10:00:00Z</when>
        <gx:coord>139.7798 35.5494 50</gx:coord>
      </gx:Track>
    </Placemark>
  </Document>
</kml>
XML;

        $points = $this->service->parseKml($kml);

        // Should pick the 4-point gx:Track, not the 2-point LineString
        $this->assertCount(4, $points);
    }

    public function test_parses_gpx_route_format(): void
    {
        // Some exports use <rte>/<rtept> instead of <trk>/<trkpt>
        $gpx = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1">
  <rte>
    <rtept lat="25.0777" lon="121.2325"><ele>100</ele></rtept>
    <rtept lat="25.2000" lon="121.5000"><ele>10000</ele></rtept>
    <rtept lat="35.5494" lon="139.7798"><ele>50</ele></rtept>
  </rte>
</gpx>
XML;

        $points = $this->service->parseGpx($gpx);

        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(25.0777, $points[0][0], 0.0001);
        $this->assertEqualsWithDelta(121.2325, $points[0][1], 0.0001);
    }

    public function test_radial_prefilter_removes_clusters(): void
    {
        // Many points very close together should be collapsed
        $points = [];
        for ($i = 0; $i < 50; $i++) {
            $points[] = [25.0 + $i * 0.0001, 121.0 + $i * 0.0001]; // ~11m apart
        }
        // Add a distant point
        $points[] = [26.0, 122.0];

        $simplified = $this->service->simplify($points, 0.005);

        // Should be drastically reduced from 51 points
        $this->assertLessThan(10, count($simplified));
        // Must keep first and last
        $this->assertEquals($points[0], $simplified[0]);
        $this->assertEquals([26.0, 122.0], $simplified[count($simplified) - 1]);
    }
}
