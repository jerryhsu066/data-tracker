<?php

namespace App\Services;

use App\Models\Airport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirportService
{
    /**
     * Find an airport by IATA code, fetching from external sources if not in DB.
     * Returns null only if all sources fail.
     */
    public function findOrFetch(string $iata, ?string $aviationstackKey = null): ?Airport
    {
        $iata = strtoupper($iata);

        $airport = Airport::where('iata', $iata)->first();
        if ($airport) {
            return $airport;
        }

        // Try AviationStack airports API (if key available)
        if ($aviationstackKey) {
            $airport = $this->fetchFromAviationStack($iata, $aviationstackKey);
            if ($airport) return $airport;
        }

        return null;
    }

    /**
     * Get the IANA timezone for an airport, with fallback chain:
     * DB → external fetch → longitude estimation → app timezone.
     */
    public function timezone(string $iata, ?string $aviationstackKey = null, ?string $fallbackTz = null): string
    {
        $airport = $this->findOrFetch($iata, $aviationstackKey);

        if ($airport?->tz) {
            return $airport->tz;
        }

        // Estimate from longitude if we have coordinates
        if ($airport?->lng) {
            return $this->timezoneFromLongitude($airport->lng);
        }

        return $fallbackTz ?? config('app.timezone');
    }

    /**
     * Resolve departure and arrival timezones for a flight.
     */
    public function resolveTimezones(
        ?string $depAirport,
        ?string $arrAirport,
        ?string $aviationstackKey = null,
    ): array {
        $depTz = $depAirport ? $this->timezone($depAirport, $aviationstackKey) : config('app.timezone');
        $arrTz = $arrAirport ? $this->timezone($arrAirport, $aviationstackKey) : config('app.timezone');

        return [$depTz, $arrTz];
    }

    /**
     * Fetch airport data from AviationStack and persist to DB.
     */
    private function fetchFromAviationStack(string $iata, string $apiKey): ?Airport
    {
        try {
            $response = Http::timeout(10)->get('http://api.aviationstack.com/v1/airports', [
                'access_key' => $apiKey,
                'iata_code'  => $iata,
            ]);

            if (!$response->successful()) return null;

            $data = $response->json('data');
            if (empty($data) || !is_array($data)) return null;

            $item = $data[0];

            return Airport::updateOrCreate(
                ['iata' => $iata],
                [
                    'name'    => $item['airport_name'] ?? $iata,
                    'city'    => $item['city_iata_code'] ?? $item['municipality'] ?? '',
                    'country' => $item['country_name'] ?? '',
                    'lat'     => (float) ($item['latitude'] ?? 0),
                    'lng'     => (float) ($item['longitude'] ?? 0),
                    'tz'      => $item['timezone'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            Log::debug("AirportService AviationStack fetch failed for {$iata}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Rough timezone estimation from longitude.
     * Returns the closest standard UTC offset timezone identifier.
     */
    private function timezoneFromLongitude(float $lng): string
    {
        $offset = (int) round($lng / 15);
        $offset = max(-12, min(14, $offset));

        // Map UTC offsets to representative IANA timezone names
        $map = [
            -12 => 'Pacific/Kwajalein',
            -11 => 'Pacific/Pago_Pago',
            -10 => 'Pacific/Honolulu',
            -9  => 'America/Anchorage',
            -8  => 'America/Los_Angeles',
            -7  => 'America/Denver',
            -6  => 'America/Chicago',
            -5  => 'America/New_York',
            -4  => 'America/Halifax',
            -3  => 'America/Sao_Paulo',
            -2  => 'Atlantic/South_Georgia',
            -1  => 'Atlantic/Azores',
            0   => 'UTC',
            1   => 'Europe/Paris',
            2   => 'Europe/Helsinki',
            3   => 'Asia/Riyadh',
            4   => 'Asia/Dubai',
            5   => 'Asia/Karachi',
            6   => 'Asia/Dhaka',
            7   => 'Asia/Bangkok',
            8   => 'Asia/Shanghai',
            9   => 'Asia/Tokyo',
            10  => 'Australia/Sydney',
            11  => 'Pacific/Noumea',
            12  => 'Pacific/Auckland',
            13  => 'Pacific/Tongatapu',
            14  => 'Pacific/Kiritimati',
        ];

        return $map[$offset] ?? 'UTC';
    }
}
