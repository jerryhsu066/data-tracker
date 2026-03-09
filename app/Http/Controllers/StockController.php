<?php

namespace App\Http\Controllers;

use App\Jobs\FetchStockPrice;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Stock::orderBy('symbol')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['symbol' => strtoupper($request->input('symbol', ''))]);

        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:15', 'regex:/^[A-Z0-9]+(\.[A-Z]+)?$/', Rule::unique('stocks', 'symbol')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $stock = Stock::create($validated);

        return response()->json($stock, 201);
    }

    public function show(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();

        return response()->json($stock);
    }

    public function destroy(string $symbol): Response
    {
        Stock::where('symbol', strtoupper($symbol))->firstOrFail()->delete();

        return response()->noContent();
    }

    public function transactions(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();

        return response()->json(
            $stock->transactions()->with('stock')->orderByDesc('transacted_at')->get()
        );
    }

    public function fetch(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();

        FetchStockPrice::dispatch($stock);

        return response()->json(['message' => 'Price fetch queued.'], 202);
    }
}
