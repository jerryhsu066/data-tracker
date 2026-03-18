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

        // Tier 1 — keyless (no user API key required)
        $airport = $this->fetchFromAirportData($iata);
        if ($airport) return $airport;

        // Tier 2 — AviationStack (requires user API key)
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
     * Fetch airport data from airport-data.com (keyless) and persist to DB.
     * Response includes IATA, name, location, country, country_code, lat, lng, and IANA tz.
     */
    private function fetchFromAirportData(string $iata): ?Airport
    {
        try {
            $response = Http::timeout(10)->get('https://www.airport-data.com/api/ap_info.json', [
                'iata' => $iata,
            ]);

            if (!$response->successful()) return null;

            $item = $response->json();
            if (empty($item) || ($item['iata'] ?? '') !== $iata) return null;

            // "location" is typically "City, Region" or "City, Region, Country"
            $city = trim(explode(',', $item['location'] ?? '')[0]) ?: $iata;
            $country = $item['country'] ?? '';
            $countryCode = $item['country_code'] ?? self::countryNameToCode($country);

            return Airport::updateOrCreate(
                ['iata' => $iata],
                [
                    'name'         => $item['name'] ?? $iata,
                    'city'         => $city,
                    'country'      => $country,
                    'country_code' => $countryCode,
                    'lat'          => (float) ($item['lat'] ?? 0),
                    'lng'          => (float) ($item['lng'] ?? 0),
                    'tz'           => $item['tz'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            Log::debug("AirportService airport-data.com fetch failed for {$iata}: {$e->getMessage()}");
            return null;
        }
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

            $country = $item['country_name'] ?? '';

            return Airport::updateOrCreate(
                ['iata' => $iata],
                [
                    'name'         => $item['airport_name'] ?? $iata,
                    'city'         => $item['city_iata_code'] ?? $item['municipality'] ?? '',
                    'country'      => $country,
                    'country_code' => self::countryNameToCode($country),
                    'lat'          => (float) ($item['latitude'] ?? 0),
                    'lng'          => (float) ($item['longitude'] ?? 0),
                    'tz'           => $item['timezone'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            Log::debug("AirportService AviationStack fetch failed for {$iata}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Map a country name string to its ISO 3166-1 alpha-2 code.
     * Covers all countries present in the airport seeder plus common API variations.
     */
    public static function countryNameToCode(string $country): ?string
    {
        static $map = [
            'Afghanistan' => 'AF', 'Albania' => 'AL', 'Algeria' => 'DZ', 'Andorra' => 'AD',
            'Angola' => 'AO', 'Argentina' => 'AR', 'Armenia' => 'AM', 'Australia' => 'AU',
            'Austria' => 'AT', 'Azerbaijan' => 'AZ', 'Bahamas' => 'BS', 'Bahrain' => 'BH',
            'Bangladesh' => 'BD', 'Belarus' => 'BY', 'Belgium' => 'BE', 'Belize' => 'BZ',
            'Bolivia' => 'BO', 'Bosnia and Herzegovina' => 'BA', 'Brazil' => 'BR',
            'Brunei' => 'BN', 'Bulgaria' => 'BG', 'Cambodia' => 'KH', 'Canada' => 'CA',
            'Chile' => 'CL', 'China' => 'CN', 'Colombia' => 'CO', 'Costa Rica' => 'CR',
            'Croatia' => 'HR', 'Cuba' => 'CU', 'Cyprus' => 'CY', 'Czech Republic' => 'CZ',
            'Denmark' => 'DK', 'Dominican Republic' => 'DO', 'Ecuador' => 'EC',
            'Egypt' => 'EG', 'El Salvador' => 'SV', 'Estonia' => 'EE', 'Ethiopia' => 'ET',
            'Fiji' => 'FJ', 'Finland' => 'FI', 'France' => 'FR', 'French Polynesia' => 'PF',
            'Georgia' => 'GE', 'Germany' => 'DE', 'Ghana' => 'GH', 'Greece' => 'GR',
            'Guam' => 'GU', 'Guatemala' => 'GT', 'Honduras' => 'HN', 'Hong Kong' => 'HK',
            'Hungary' => 'HU', 'Iceland' => 'IS', 'India' => 'IN', 'Indonesia' => 'ID',
            'Iran' => 'IR', 'Iraq' => 'IQ', 'Ireland' => 'IE', 'Israel' => 'IL',
            'Italy' => 'IT', 'Ivory Coast' => 'CI', 'Jamaica' => 'JM', 'Japan' => 'JP',
            'Jordan' => 'JO', 'Kazakhstan' => 'KZ', 'Kenya' => 'KE', 'Kiribati' => 'KI',
            'Kuwait' => 'KW', 'Kyrgyzstan' => 'KG', 'Laos' => 'LA', 'Latvia' => 'LV',
            'Lebanon' => 'LB', 'Lithuania' => 'LT', 'Luxembourg' => 'LU', 'Macau' => 'MO',
            'Madagascar' => 'MG', 'Malaysia' => 'MY', 'Maldives' => 'MV', 'Mali' => 'ML',
            'Malta' => 'MT', 'Mauritius' => 'MU', 'Mexico' => 'MX', 'Micronesia' => 'FM',
            'Moldova' => 'MD', 'Mongolia' => 'MN', 'Montenegro' => 'ME', 'Morocco' => 'MA',
            'Mozambique' => 'MZ', 'Myanmar' => 'MM', 'Namibia' => 'NA', 'Nepal' => 'NP',
            'Netherlands' => 'NL', 'New Caledonia' => 'NC', 'New Zealand' => 'NZ',
            'Nicaragua' => 'NI', 'Nigeria' => 'NG', 'North Korea' => 'KP',
            'North Macedonia' => 'MK', 'Northern Mariana Islands' => 'MP', 'Norway' => 'NO',
            'Oman' => 'OM', 'Pakistan' => 'PK', 'Palau' => 'PW', 'Panama' => 'PA',
            'Papua New Guinea' => 'PG', 'Paraguay' => 'PY', 'Peru' => 'PE',
            'Philippines' => 'PH', 'Poland' => 'PL', 'Portugal' => 'PT', 'Qatar' => 'QA',
            'Romania' => 'RO', 'Russia' => 'RU', 'Rwanda' => 'RW', 'Samoa' => 'WS',
            'Saudi Arabia' => 'SA', 'Senegal' => 'SN', 'Serbia' => 'RS',
            'Seychelles' => 'SC', 'Singapore' => 'SG', 'Sint Maarten' => 'SX',
            'Slovakia' => 'SK', 'Slovenia' => 'SI', 'Solomon Islands' => 'SB',
            'South Africa' => 'ZA', 'South Korea' => 'KR', 'Spain' => 'ES',
            'Sri Lanka' => 'LK', 'Sweden' => 'SE', 'Switzerland' => 'CH', 'Taiwan' => 'TW',
            'Tajikistan' => 'TJ', 'Tanzania' => 'TZ', 'Thailand' => 'TH',
            'Timor-Leste' => 'TL', 'Tonga' => 'TO', 'Trinidad and Tobago' => 'TT',
            'Tunisia' => 'TN', 'Turkey' => 'TR', 'Turkmenistan' => 'TM', 'UAE' => 'AE',
            'Uganda' => 'UG', 'Ukraine' => 'UA', 'United Arab Emirates' => 'AE',
            'United Kingdom' => 'GB', 'United States' => 'US', 'Uruguay' => 'UY',
            'Uzbekistan' => 'UZ', 'Vanuatu' => 'VU', 'Venezuela' => 'VE', 'Vietnam' => 'VN',
            'Yemen' => 'YE', 'Zambia' => 'ZM', 'Zimbabwe' => 'ZW',
        ];

        return $map[$country] ?? null;
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
