<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = Stock::with('transactions')
            ->whereHas('transactions')
            ->get()
            ->map(fn (Stock $stock) => $this->buildPosition($stock))
            ->filter(fn (array $pos) => (float) $pos['net_shares'] > 0)
            ->values();

        return response()->json($positions);
    }

    private function buildPosition(Stock $stock): array
    {
        $transactions = $stock->transactions;

        $buys = $transactions->where('type', 'buy');
        $sells = $transactions->where('type', 'sell');

        $totalBuyShares = $buys->sum('shares');
        $totalBuyCost = $buys->sum(fn ($t) => (float) $t->shares * (float) $t->price_per_share);

        $totalSellShares = $sells->sum('shares');
        $totalSellRevenue = $sells->sum(fn ($t) => (float) $t->shares * (float) $t->price_per_share);

        $netShares = $totalBuyShares - $totalSellShares;

        // Weighted average cost (buy side only — doesn't change on sell)
        $averageCost = $totalBuyShares > 0 ? $totalBuyCost / $totalBuyShares : 0;

        $currentPrice = (float) ($stock->current_price ?? 0);
        $currentValue = $netShares * $currentPrice;
        $unrealizedGain = ($currentPrice - $averageCost) * $netShares;
        $realizedGain = $totalSellRevenue - ($totalSellShares * $averageCost);

        return [
            'stock' => $stock->only(['id', 'symbol', 'name', 'current_price', 'change_percent', 'last_fetched_at']),
            'net_shares' => number_format($netShares, 4, '.', ''),
            'average_cost' => number_format($averageCost, 4, '.', ''),
            'current_value' => number_format($currentValue, 4, '.', ''),
            'unrealized_gain' => number_format($unrealizedGain, 4, '.', ''),
            'realized_gain' => number_format($realizedGain, 4, '.', ''),
        ];
    }
}
