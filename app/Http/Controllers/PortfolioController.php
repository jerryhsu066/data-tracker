<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $discount = (float) ($request->user()->handling_fee_discount ?? 0);

        $positions = Stock::with(['transactions' => fn ($q) => $q->where('user_id', $userId)])
            ->whereHas('transactions', fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->map(fn (Stock $stock) => $this->buildPosition($stock, $discount))
            ->filter(fn (array $pos) => (float) $pos['net_shares'] > 0)
            ->values();

        return response()->json($positions);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // All transactions for this user, ordered by date
        $transactions = Transaction::where('user_id', $userId)
            ->with('stock')
            ->orderBy('transacted_at')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([]);
        }

        $stockIds = $transactions->pluck('stock_id')->unique()->values();

        // All price history records for the relevant stocks
        $histories = StockPriceHistory::whereIn('stock_id', $stockIds)
            ->orderBy('date')
            ->get()
            ->groupBy('stock_id');

        if ($histories->isEmpty()) {
            return response()->json([]);
        }

        // Build a sorted list of all available dates across all stocks
        $allDates = $histories->flatten()->pluck('date')->map(fn ($d) => (string) $d)->unique()->sort()->values();

        // For each date, compute sum(sharesHeld × closePrice) for each stock
        $result = [];
        foreach ($allDates as $date) {
            $totalValue = 0.0;

            foreach ($stockIds as $stockId) {
                // Net shares held on this date (all transactions up to and including this date)
                $txUpToDate = $transactions
                    ->where('stock_id', $stockId)
                    ->filter(fn ($t) => (string) $t->transacted_at <= $date);

                $netShares = $txUpToDate->sum(fn ($t) => $t->type === 'buy'
                    ? (float) $t->shares
                    : -(float) $t->shares
                );

                if ($netShares <= 0) {
                    continue;
                }

                // Close price on this date for this stock
                $priceRecord = ($histories[$stockId] ?? collect())
                    ->first(fn ($h) => (string) $h->date === $date);

                if ($priceRecord) {
                    $totalValue += $netShares * (float) $priceRecord->close_price;
                }
            }

            if ($totalValue > 0) {
                $result[] = ['date' => $date, 'value' => round($totalValue, 2)];
            }
        }

        return response()->json($result);
    }

    private function buildPosition(Stock $stock, float $discount): array
    {
        $transactions = $stock->transactions;

        $buys = $transactions->where('type', 'buy');
        $sells = $transactions->where('type', 'sell');

        $totalBuyShares = $buys->sum('shares');
        $totalBuyCost = $buys->sum(fn ($t) => (float) $t->shares * (float) $t->price_per_share + (float) $t->handling_fee);

        $totalSellShares = $sells->sum('shares');
        $totalSellRevenue = $sells->sum(fn ($t) => (float) $t->shares * (float) $t->price_per_share - (float) $t->handling_fee - (float) $t->transaction_tax);

        $netShares = $totalBuyShares - $totalSellShares;

        $averageCost = $totalBuyShares > 0 ? $totalBuyCost / $totalBuyShares : 0;

        $currentPrice = (float) ($stock->current_price ?? 0);
        $currentValue = $netShares * $currentPrice;

        // Deduct estimated sell fees from unrealized gain so it reflects actual net proceeds.
        $unrealizedGain = 0.0;
        if ($netShares > 0 && $currentPrice > 0) {
            $sellHandlingFee = (int) max(20, floor($currentValue * 0.001425 * (1 - $discount)));
            $sellTax = (int) floor($currentValue * 0.003);
            $unrealizedGain = $currentValue - ($averageCost * $netShares) - $sellHandlingFee - $sellTax;
        }

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
