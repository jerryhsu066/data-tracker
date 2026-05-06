<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Flight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFlightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = AppSetting::get()->public_flight_user_id;

        $query = Flight::where('user_id', $userId);

        if ($request->filled('year')) {
            $query->whereYear('flight_date', $request->year);
        }

        return response()->json($query->orderByDesc('flight_date')->orderByDesc('departure_time')->get());
    }
}
