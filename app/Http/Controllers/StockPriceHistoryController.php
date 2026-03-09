<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\JsonResponse;

class StockPriceHistoryController extends Controller
{
    public function index(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();

        return response()->json(
            $stock->priceHistories()->orderBy('date')->get()
        );
    }
}
