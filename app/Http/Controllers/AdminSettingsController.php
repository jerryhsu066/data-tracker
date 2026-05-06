<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        return response()->json(AppSetting::get()->only(['registration_enabled', 'public_flight_user_id']));
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'registration_enabled'  => ['sometimes', 'boolean'],
            'public_flight_user_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $settings = AppSetting::get();
        $settings->update($validated);

        return response()->json($settings->only(['registration_enabled', 'public_flight_user_id']));
    }
}
