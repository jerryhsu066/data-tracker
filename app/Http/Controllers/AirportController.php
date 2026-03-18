<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\FlightSetting;
use App\Services\AirportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Airport::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('iata', 'like', "%{$term}%")
                  ->orWhere('city', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%");
            });
        }

        return response()->json(
            $query->orderBy('iata')->get(['iata', 'name', 'city', 'country', 'country_code', 'lat', 'lng', 'tz'])
        );
    }

    public function show(Request $request, string $iata, AirportService $service): JsonResponse
    {
        $iata = strtoupper($iata);

        // Check DB first
        $airport = Airport::where('iata', $iata)->first();

        // If not found, try on-demand fetch using user's AviationStack key
        if (!$airport) {
            $settings = FlightSetting::where('user_id', $request->user()->id)->first();
            $airport = $service->findOrFetch($iata, $settings?->aviationstack_key);
        }

        if (!$airport) {
            return response()->json(['message' => 'Airport not found'], 404);
        }

        return response()->json($airport->only(['iata', 'name', 'city', 'country', 'country_code', 'lat', 'lng', 'tz']));
    }
}
