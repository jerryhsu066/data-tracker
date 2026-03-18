<?php

namespace App\Services;

use App\Models\FlightSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlightLookupService
{
    public function __construct(private AirportService $airportService) {}

    public function lookup(string $flightNumber, string $flightDate, ?FlightSetting $settings = null): ?array
    {
        $aviationstackKey = $settings?->aviationstack_key;

        // Tier 1 — keyless sources
        $result = $this->tryFlightRadar24($flightNumber, $flightDate, $aviationstackKey);
        if ($result) return $result;

        // Tier 2 — keyed sources (better for historical dates)
        if ($aviationstackKey) {
            $result = $this->tryAviationStack($flightNumber, $flightDate, $aviationstackKey);
            if ($result) return $result;
        }

        if ($settings?->aerodatabox_key) {
            $result = $this->tryAeroDataBox($flightNumber, $flightDate, $settings->aerodatabox_key, $aviationstackKey);
            if ($result) return $result;
        }

        return null;
    }

    private function tryFlightRadar24(string $flightNumber, string $flightDate, ?string $aviationstackKey): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://api.flightradar24.com/common/v1/flight/list.json', [
                'query'   => strtoupper($flightNumber),
                'fetchBy' => 'flight',
            ]);

            if (!$response->successful()) return null;

            $flights = $response->json('result.response.data');
            if (empty($flights) || !is_array($flights)) return null;

            $targetDate = $flightDate;

            // Resolve airport IATA codes and timezones
            $depAirport = $flights[0]['airport']['origin']['code']['iata'] ?? null;
            $arrAirport = $flights[0]['airport']['destination']['code']['iata'] ?? null;
            [$depTz, $arrTz] = $this->airportService->resolveTimezones($depAirport, $arrAirport, $aviationstackKey);

            // Try to find an exact date match (compare in departure airport's timezone)
            $match = null;
            foreach ($flights as $f) {
                $depTs = $f['time']['scheduled']['departure'] ?? null;
                if ($depTs && Carbon::createFromTimestamp($depTs)->setTimezone($depTz)->format('Y-m-d') === $targetDate) {
                    $match = $f;
                    break;
                }
            }

            // If no exact date match, use the first result as a route/aircraft template.
            if (!$match) {
                $match = $flights[0];
            }

            $airline = $match['airline']['name'] ?? null;

            // If airline is null in the response, try to find it from another result
            if (!$airline) {
                foreach ($flights as $f) {
                    if (!empty($f['airline']['name'])) {
                        $airline = $f['airline']['name'];
                        break;
                    }
                }
            }

            // Derive scheduled times for the requested date from the matched entry.
            // Departure time is shown in the departure airport's local timezone,
            // arrival time in the arrival airport's local timezone.
            $departureTime = null;
            $arrivalTime   = null;
            $depTs = $match['time']['scheduled']['departure'] ?? null;
            $arrTs = $match['time']['scheduled']['arrival'] ?? null;
            $isTemplate = $depTs && Carbon::createFromTimestamp($depTs)->setTimezone($depTz)->format('Y-m-d') !== $targetDate;

            if ($depTs) {
                $depCarbon = Carbon::createFromTimestamp($depTs)->setTimezone($depTz);

                if ($isTemplate) {
                    // Template match: use the time-of-day on the target date
                    $departureTime = "{$targetDate}T{$depCarbon->format('H:i')}";

                    if ($arrTs) {
                        $duration = $arrTs - $depTs;
                        $arrivalTime = Carbon::parse("{$targetDate} {$depCarbon->format('H:i:s')}", $depTz)
                            ->addSeconds($duration)
                            ->setTimezone($arrTz)
                            ->format('Y-m-d\TH:i');
                    }
                } else {
                    $departureTime = $depCarbon->format('Y-m-d\TH:i');
                    if ($arrTs) {
                        $arrivalTime = Carbon::createFromTimestamp($arrTs)->setTimezone($arrTz)->format('Y-m-d\TH:i');
                    }
                }
            }

            // Tail number (registration) is aircraft-instance-specific — each date
            // uses a different physical aircraft. Only return it for exact date matches.
            $tailNumber = $isTemplate ? null : ($match['aircraft']['registration'] ?? null);

            return [
                'airline'           => $airline,
                'departure_airport' => $depAirport,
                'arrival_airport'   => $arrAirport,
                'departure_time'    => $departureTime,
                'arrival_time'      => $arrivalTime,
                'aircraft_type'     => $match['aircraft']['model']['code'] ?? null,
                'tail_number'       => $tailNumber,
                'source'            => 'flightradar24',
            ];
        } catch (\Throwable $e) {
            Log::debug("FlightLookup FR24 failed: {$e->getMessage()}");
            return null;
        }
    }

    private function tryAviationStack(string $flightNumber, string $flightDate, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)->get('http://api.aviationstack.com/v1/flights', [
                'access_key'  => $apiKey,
                'flight_iata' => strtoupper($flightNumber),
                'flight_date' => $flightDate,
            ]);

            if (!$response->successful()) return null;

            $data = $response->json('data');
            if (empty($data) || !is_array($data)) return null;

            $flight = $data[0];

            $depAirport = $flight['departure']['iata'] ?? null;
            $arrAirport = $flight['arrival']['iata'] ?? null;
            $depTime = $flight['departure']['scheduled'] ?? null;
            $arrTime = $flight['arrival']['scheduled'] ?? null;
            [$depTz, $arrTz] = $this->airportService->resolveTimezones($depAirport, $arrAirport, $apiKey);

            return [
                'airline'           => $flight['airline']['name'] ?? null,
                'departure_airport' => $depAirport,
                'arrival_airport'   => $arrAirport,
                'departure_time'    => $depTime ? Carbon::parse($depTime)->setTimezone($depTz)->format('Y-m-d\TH:i') : null,
                'arrival_time'      => $arrTime ? Carbon::parse($arrTime)->setTimezone($arrTz)->format('Y-m-d\TH:i') : null,
                'aircraft_type'     => $flight['aircraft']['iata'] ?? null,
                'tail_number'       => $flight['aircraft']['registration'] ?? null,
                'source'            => 'aviationstack',
            ];
        } catch (\Throwable $e) {
            Log::debug("FlightLookup AviationStack failed: {$e->getMessage()}");
            return null;
        }
    }

    private function tryAeroDataBox(string $flightNumber, string $flightDate, string $rapidApiKey, ?string $aviationstackKey): ?array
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-RapidAPI-Key'  => $rapidApiKey,
                'X-RapidAPI-Host' => 'aerodatabox.p.rapidapi.com',
            ])->get("https://aerodatabox.p.rapidapi.com/flights/number/" . urlencode(strtoupper($flightNumber)) . "/{$flightDate}");

            if (!$response->successful()) return null;

            $flights = $response->json();
            if (empty($flights) || !is_array($flights)) return null;

            $flight = $flights[0];

            $depAirport = $flight['departure']['airport']['iata'] ?? null;
            $arrAirport = $flight['arrival']['airport']['iata'] ?? null;
            $depTime = $flight['departure']['scheduledTime']['utc'] ?? null;
            $arrTime = $flight['arrival']['scheduledTime']['utc'] ?? null;
            [$depTz, $arrTz] = $this->airportService->resolveTimezones($depAirport, $arrAirport, $aviationstackKey);

            return [
                'airline'           => $flight['airline']['name'] ?? null,
                'departure_airport' => $depAirport,
                'arrival_airport'   => $arrAirport,
                'departure_time'    => $depTime ? Carbon::parse($depTime)->setTimezone($depTz)->format('Y-m-d\TH:i') : null,
                'arrival_time'      => $arrTime ? Carbon::parse($arrTime)->setTimezone($arrTz)->format('Y-m-d\TH:i') : null,
                'aircraft_type'     => $flight['aircraft']['model'] ?? null,
                'tail_number'       => $flight['aircraft']['reg'] ?? null,
                'source'            => 'aerodatabox',
            ];
        } catch (\Throwable $e) {
            Log::debug("FlightLookup AeroDataBox failed: {$e->getMessage()}");
            return null;
        }
    }
}
