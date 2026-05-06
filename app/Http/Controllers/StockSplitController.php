<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockSplit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StockSplitController extends Controller
{
    public function index(Stock $stock): JsonResponse
    {
        return response()->json($stock->splits()->orderBy('split_date')->get());
    }

    public function store(Request $request, Stock $stock): JsonResponse
    {
        $validated = $request->validate([
            'split_date' => ['required', 'date'],
            'ratio_from' => ['required', 'integer', 'min:1'],
            'ratio_to'   => ['required', 'integer', 'min:1'],
        ]);

        $split = $stock->splits()->create($validated);

        return response()->json($split, 201);
    }

    public function destroy(Stock $stock, StockSplit $split): Response
    {
        abort_if($split->stock_id !== $stock->id, 404);

        $split->delete();

        return response()->noContent();
    }
}
