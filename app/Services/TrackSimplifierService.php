<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class TrackSimplifierService
{
    /**
     * Parse GPX XML and extract [lat, lng] points.
     * Supports <trk>/<trkseg>/<trkpt> and <rte>/<rtept> formats.
     */
    public function parseGpx(string $xml): array
    {
        $doc = new \SimpleXMLElement($xml);

        // Try <trk>/<trkseg>/<trkpt> (track format)
        $trackPoints = [];
        foreach ($doc->trk as $trk) {
            foreach ($trk->trkseg as $seg) {
                foreach ($seg->trkpt as $pt) {
                    $trackPoints[] = [
                        (float) $pt['lat'],
                        (float) $pt['lon'],
                    ];
                }
            }
        }

        // Try <rte>/<rtept> (route format)
        $routePoints = [];
        foreach ($doc->rte as $rte) {
            foreach ($rte->rtept as $pt) {
                $routePoints[] = [
                    (float) $pt['lat'],
                    (float) $pt['lon'],
                ];
            }
        }

        // Return whichever has more points
        return count($trackPoints) >= count($routePoints) ? $trackPoints : $routePoints;
    }

    /**
     * Parse KML XML and extract [lat, lng] points.
     * Supports <LineString>/<coordinates> and <gx:Track>/<gx:coord> formats.
     */
    public function parseKml(string $xml): array
    {
        $doc = new \SimpleXMLElement($xml);
        $doc->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
        $doc->registerXPathNamespace('gx', 'http://www.google.com/kml/ext/2.2');

        // Try <gx:Track>/<gx:coord> (FlightRadar24, FlightAware format)
        // gx:coord uses space-separated "lng lat alt"
        $gxPoints = [];
        $gxCoords = $doc->xpath('//gx:Track/gx:coord');
        if ($gxCoords) {
            foreach ($gxCoords as $coord) {
                $parts = preg_split('/\s+/', trim((string) $coord));
                if (count($parts) >= 2) {
                    $gxPoints[] = [
                        (float) $parts[1], // lat
                        (float) $parts[0], // lng
                    ];
                }
            }
        }

        // Try <LineString>/<coordinates> (standard KML format)
        // coordinates use "lng,lat,alt" comma-separated
        $linePoints = [];
        $coordNodes = $doc->xpath('//kml:coordinates');
        if ($coordNodes) {
            foreach ($coordNodes as $node) {
                $raw = trim((string) $node);
                $lines = preg_split('/\s+/', $raw);

                foreach ($lines as $line) {
                    $parts = explode(',', trim($line));
                    if (count($parts) >= 2) {
                        $linePoints[] = [
                            (float) $parts[1], // lat
                            (float) $parts[0], // lng
                        ];
                    }
                }
            }
        }

        // Return whichever has more points
        return count($gxPoints) >= count($linePoints) ? $gxPoints : $linePoints;
    }

    /**
     * Two-pass simplification: radial pre-filter + Ramer-Douglas-Peucker.
     */
    public function simplify(array $points, float $epsilon = 0.005): array
    {
        if (count($points) <= 2) {
            return $points;
        }

        // Pass 1: radial distance pre-filter (~0.002° ≈ 200m)
        $filtered = [$points[0]];
        $radialThreshold = 0.002;

        for ($i = 1; $i < count($points); $i++) {
            $last = $filtered[count($filtered) - 1];
            $dist = sqrt(
                pow($points[$i][0] - $last[0], 2) +
                pow($points[$i][1] - $last[1], 2)
            );
            if ($dist >= $radialThreshold) {
                $filtered[] = $points[$i];
            }
        }

        // Always keep the last point
        $lastPoint = $points[count($points) - 1];
        if ($filtered[count($filtered) - 1] !== $lastPoint) {
            $filtered[] = $lastPoint;
        }

        if (count($filtered) <= 2) {
            return $filtered;
        }

        // Pass 2: Ramer-Douglas-Peucker
        return $this->rdp($filtered, $epsilon);
    }

    /**
     * Detect format by extension, parse, simplify, return points.
     */
    public function parseAndSimplify(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = file_get_contents($file->getRealPath());

        $points = match ($extension) {
            'gpx' => $this->parseGpx($content),
            'kml' => $this->parseKml($content),
            default => throw new \InvalidArgumentException("Unsupported file format: {$extension}"),
        };

        return $this->simplify($points);
    }

    /**
     * Ramer-Douglas-Peucker algorithm (recursive).
     */
    private function rdp(array $points, float $epsilon): array
    {
        $count = count($points);
        if ($count <= 2) {
            return $points;
        }

        $first = $points[0];
        $last = $points[$count - 1];

        $maxDist = 0;
        $maxIndex = 0;

        for ($i = 1; $i < $count - 1; $i++) {
            $dist = $this->perpendicularDistance($points[$i], $first, $last);
            if ($dist > $maxDist) {
                $maxDist = $dist;
                $maxIndex = $i;
            }
        }

        if ($maxDist > $epsilon) {
            $left = $this->rdp(array_slice($points, 0, $maxIndex + 1), $epsilon);
            $right = $this->rdp(array_slice($points, $maxIndex), $epsilon);

            // Merge, removing duplicate middle point
            array_pop($left);
            return array_merge($left, $right);
        }

        return [$first, $last];
    }

    /**
     * Perpendicular distance from a point to a line defined by two endpoints.
     */
    private function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $dx = $lineEnd[1] - $lineStart[1];
        $dy = $lineEnd[0] - $lineStart[0];

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt(
                pow($point[0] - $lineStart[0], 2) +
                pow($point[1] - $lineStart[1], 2)
            );
        }

        $numerator = abs(
            $dy * ($point[1] - $lineStart[1]) -
            $dx * ($point[0] - $lineStart[0])
        );

        $denominator = sqrt($dx * $dx + $dy * $dy);

        return $numerator / $denominator;
    }
}
