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
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handling_fee_discount' => ['required', 'numeric', 'gte:0', 'lte:1'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'handling_fee_discount' => $request->user()->handling_fee_discount,
        ]);
    }
}
