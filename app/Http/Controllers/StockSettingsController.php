<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'handling_fee_discount' => $request->user()->handling_fee_discount,
            'gain_is_red'           => (bool) $request->user()->gain_is_red,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handling_fee_discount' => ['sometimes', 'numeric', 'gte:0', 'lte:1'],
            'gain_is_red'           => ['sometimes', 'boolean'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'handling_fee_discount' => $request->user()->handling_fee_discount,
            'gain_is_red'           => (bool) $request->user()->gain_is_red,
        ]);
    }
}
