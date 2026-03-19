<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\FlightSetting;
use App\Services\AirportService;
use App\Services\FlightLookupService;
use App\Services\Fr24ImportService;
use App\Services\TrackSimplifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FlightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Flight::where('user_id', $request->user()->id);

        if ($request->filled('year')) {
            $query->whereYear('flight_date', $request->year);
        }

        return response()->json($query->orderByDesc('flight_date')->orderByDesc('departure_time')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flight_date'       => ['required', 'date'],
            'airline'           => ['required', 'string', 'max:100'],
            'flight_number'     => ['required', 'string', 'max:20'],
            'departure_airport' => ['required', 'string', 'size:3'],
            'arrival_airport'   => ['required', 'string', 'size:3'],
            'departure_time'    => ['nullable', 'date'],
            'arrival_time'      => ['nullable', 'date'],
            'aircraft_type'     => ['nullable', 'string', 'max:50'],
            'seat_class'        => ['nullable', 'string', 'max:20'],
            'seat_number'       => ['nullable', 'string', 'max:10'],
            'booking_reference' => ['nullable', 'string', 'max:20'],
            'ticket_price'      => ['nullable', 'numeric', 'min:0'],
            'tail_number'       => ['nullable', 'string', 'max:20'],
            'notes'             => ['nullable', 'string'],
        ]);

        $flight = Flight::create(['user_id' => $request->user()->id, ...$validated]);

        // Ensure airports exist in DB so the map can always show routes
        $this->resolveAirports($request->user()->id, $validated['departure_airport'], $validated['arrival_airport']);

        return response()->json($flight->fresh(), 201);
    }

    public function update(Request $request, Flight $flight): JsonResponse
    {
        Gate::authorize('update', $flight);

        $validated = $request->validate([
            'flight_date'       => ['sometimes', 'date'],
            'airline'           => ['sometimes', 'string', 'max:100'],
            'flight_number'     => ['sometimes', 'string', 'max:20'],
            'departure_airport' => ['sometimes', 'string', 'size:3'],
            'arrival_airport'   => ['sometimes', 'string', 'size:3'],
            'departure_time'    => ['nullable', 'date'],
            'arrival_time'      => ['nullable', 'date'],
            'aircraft_type'     => ['nullable', 'string', 'max:50'],
            'seat_class'        => ['nullable', 'string', 'max:20'],
            'seat_number'       => ['nullable', 'string', 'max:10'],
            'booking_reference' => ['nullable', 'string', 'max:20'],
            'ticket_price'      => ['nullable', 'numeric', 'min:0'],
            'tail_number'       => ['nullable', 'string', 'max:20'],
            'notes'             => ['nullable', 'string'],
        ]);

        $flight->update($validated);

        // Ensure airports exist in DB so the map can always show routes
        $dep = $validated['departure_airport'] ?? $flight->departure_airport;
        $arr = $validated['arrival_airport'] ?? $flight->arrival_airport;
        $this->resolveAirports($request->user()->id, $dep, $arr);

        return response()->json($flight->fresh());
    }

    public function destroy(Request $request, Flight $flight): Response
    {
        Gate::authorize('delete', $flight);

        $flight->delete();

        return response()->noContent();
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $flights = Flight::where('user_id', $userId)
            ->select('departure_airport', 'arrival_airport', 'airline')
            ->get();

        $totalFlights = $flights->count();

        $allCodes = $flights->pluck('departure_airport')
            ->merge($flights->pluck('arrival_airport'));

        $allAirports = $allCodes->unique()->count();

        $uniqueAirlines = $flights->pluck('airline')->unique()->count();

        $mostVisited = null;
        if ($totalFlights > 0) {
            $mostVisited = $allCodes->countBy()->sortDesc()->keys()->first();
        }

        $isSqlite = DB::getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "strftime('%Y', flight_date)" : 'YEAR(flight_date)';

        $flightsByYear = Flight::where('user_id', $userId)
            ->selectRaw("{$yearExpr} as year, count(*) as count")
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('count', 'year');

        $flightsByClass = Flight::where('user_id', $userId)
            ->whereNotNull('seat_class')
            ->selectRaw('seat_class, count(*) as count')
            ->groupBy('seat_class')
            ->pluck('count', 'seat_class');

        return response()->json([
            'total_flights'       => $totalFlights,
            'unique_airports'     => $allAirports,
            'unique_airlines'     => $uniqueAirlines,
            'most_visited_airport' => $mostVisited,
            'flights_by_year'     => $flightsByYear,
            'flights_by_class'    => $flightsByClass,
        ]);
    }

    public function lookup(Request $request, FlightLookupService $service): JsonResponse
    {
        $request->validate([
            'flight_number' => ['required', 'string'],
            'flight_date'   => ['required', 'date'],
        ]);

        $settings = FlightSetting::where('user_id', $request->user()->id)->first();

        $result = $service->lookup(
            $request->flight_number,
            $request->flight_date,
            $settings,
        );

        return response()->json($result ?? [
            'airline'           => null,
            'departure_airport' => null,
            'arrival_airport'   => null,
            'departure_time'    => null,
            'arrival_time'      => null,
            'aircraft_type'     => null,
            'tail_number'       => null,
            'source'            => null,
        ]);
    }

    public function showSettings(Request $request): JsonResponse
    {
        $settings = FlightSetting::where('user_id', $request->user()->id)->first();

        return response()->json([
            'has_aviationstack_key' => $settings?->aviationstack_key !== null,
            'has_aerodatabox_key'   => $settings?->aerodatabox_key !== null,
            'fr24_username'         => $settings?->fr24_username,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'aviationstack_key' => ['nullable', 'string', 'max:255'],
            'aerodatabox_key'   => ['nullable', 'string', 'max:255'],
            'fr24_username'     => ['nullable', 'string', 'max:100'],
        ]);

        $settings = FlightSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return response()->json([
            'has_aviationstack_key' => $settings->aviationstack_key !== null,
            'has_aerodatabox_key'   => $settings->aerodatabox_key !== null,
            'fr24_username'         => $settings->fr24_username,
        ]);
    }

    public function importFr24(Request $request, Fr24ImportService $service): JsonResponse
    {
        $settings = FlightSetting::where('user_id', $request->user()->id)->first();

        if (!$settings?->fr24_username) {
            return response()->json([
                'message' => 'FR24 username is not configured. Please set it in settings first.',
            ], 422);
        }

        $result = $service->import($settings->fr24_username, $request->user()->id);

        return response()->json($result);
    }

    public function deleteFr24Imports(Request $request): JsonResponse
    {
        $deleted = Flight::where('user_id', $request->user()->id)
            ->where('import_source', 'fr24')
            ->count();

        Flight::where('user_id', $request->user()->id)
            ->where('import_source', 'fr24')
            ->delete();

        return response()->json(['deleted' => $deleted]);
    }

    public function uploadTrack(Request $request, Flight $flight, TrackSimplifierService $simplifier): JsonResponse
    {
        Gate::authorize('update', $flight);

        $request->validate([
            'track' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('track');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['gpx', 'kml'])) {
            return response()->json(['message' => 'File must be a .gpx or .kml file.', 'errors' => ['track' => ['File must be a .gpx or .kml file.']]], 422);
        }

        try {
            $points = $simplifier->parseAndSimplify($file);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to parse track file.', 'errors' => ['track' => ['Failed to parse track file.']]], 422);
        }

        if (count($points) < 2) {
            return response()->json(['message' => 'Track must contain at least 2 points.', 'errors' => ['track' => ['Track must contain at least 2 points.']]], 422);
        }

        $flight->update(['track_points' => $points]);

        return response()->json($flight->fresh());
    }

    public function deleteTrack(Request $request, Flight $flight): JsonResponse
    {
        Gate::authorize('update', $flight);

        $flight->update(['track_points' => null]);

        return response()->json($flight->fresh());
    }

    /**
     * Ensure departure and arrival airports exist in the DB (for map display).
     * Tries on-demand fetch from external API if missing; non-blocking on failure.
     */
    private function resolveAirports(int $userId, string $depIata, string $arrIata): void
    {
        $airportService = app(AirportService::class);
        $apiKey = FlightSetting::where('user_id', $userId)->value('aviationstack_key');

        $airportService->findOrFetch($depIata, $apiKey);
        $airportService->findOrFetch($arrIata, $apiKey);
    }
}
