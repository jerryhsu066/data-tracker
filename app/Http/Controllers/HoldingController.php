<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HoldingController extends Controller
{
    public function index(): JsonResponse
    {
        $holdings = Holding::with('stock')->get()->map(fn (Holding $h) => [
            ...$h->toArray(),
            'stock' => $h->stock,
            'current_value' => $h->current_value,
            'gain_loss' => $h->gain_loss,
        ]);

        return response()->json($holdings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'shares' => ['required', 'numeric', 'gt:0'],
            'average_cost' => ['required', 'numeric', 'gt:0'],
        ]);

        $holding = Holding::create($validated);
        $holding->load('stock');

        return response()->json([
            ...$holding->toArray(),
            'current_value' => $holding->current_value,
            'gain_loss' => $holding->gain_loss,
        ], 201);
    }

    public function update(Request $request, Holding $holding): JsonResponse
    {
        $validated = $request->validate([
            'shares' => ['sometimes', 'numeric', 'gt:0'],
            'average_cost' => ['sometimes', 'numeric', 'gt:0'],
        ]);

        $holding->update($validated);
        $holding->load('stock');

        return response()->json([
            ...$holding->toArray(),
            'current_value' => $holding->current_value,
            'gain_loss' => $holding->gain_loss,
        ]);
    }

    public function destroy(Holding $holding): Response
    {
        $holding->delete();

        return response()->noContent();
    }
}
