<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class StockPriceHistoryController extends Controller
{
    public function index(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();

        $today = Carbon::today('Asia/Taipei')->toDateString();

        return response()->json(
            $stock->priceHistories()->where('date', '<=', $today)->orderBy('date')->get()
        );
    }
}
