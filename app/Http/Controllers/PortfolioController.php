<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use App\Models\StockTransaction;
use Carbon\Carbon;
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
        $transactions = StockTransaction::where('user_id', $userId)
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

        $today = Carbon::today('Asia/Taipei')->toDateString();

        // Build a sorted list of all available dates across all stocks, up to and including today.
        $allDates = $histories->flatten()
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->filter(fn ($d) => $d <= $today)
            ->unique()->sort()->values();

        // Pre-build a sorted price array per stock: [['date' => '...', 'price' => 0.0], ...]
        // This allows O(1) carry-forward lookup per (stock, date) pair instead of O(n) re-scan.
        $priceIndex = [];
        foreach ($stockIds as $stockId) {
            $priceIndex[$stockId] = ($histories[$stockId] ?? collect())
                ->filter(fn ($h) => (float) $h->close_price > 0)
                ->map(fn ($h) => ['date' => (string) $h->date, 'price' => (float) $h->close_price])
                ->values()
                ->all();
        }

        // For each date, compute sum(sharesHeld × closePrice) for each stock
        $result = [];

        // Cursor per stock pointing to the last known price index position
        $cursors = array_fill_keys($stockIds->all(), 0);

        foreach ($allDates as $date) {
            $totalValue = 0.0;
            $totalCostBasis = 0.0;

            foreach ($stockIds as $stockId) {
                $txUpToDate = $transactions
                    ->where('stock_id', $stockId)
                    ->filter(fn ($t) => (string) $t->transacted_at <= $date);

                $buysUpToDate  = $txUpToDate->where('type', 'buy');
                $sellsUpToDate = $txUpToDate->where('type', 'sell');

                $totalBuyShares  = (float) $buysUpToDate->sum('shares');
                $totalSellShares = (float) $sellsUpToDate->sum('shares');
                $netShares = $totalBuyShares - $totalSellShares;

                if ($netShares <= 0) {
                    continue;
                }

                // Advance the cursor to the last price record on or before $date
                $prices = $priceIndex[$stockId];
                $cursor = $cursors[$stockId];
                while ($cursor + 1 < count($prices) && $prices[$cursor + 1]['date'] <= $date) {
                    $cursor++;
                }
                $cursors[$stockId] = $cursor;

                $latestPrice = (isset($prices[$cursor]) && $prices[$cursor]['date'] <= $date)
                    ? $prices[$cursor]['price']
                    : null;

                if ($latestPrice !== null) {
                    $totalValue += $netShares * $latestPrice;

                    $totalBuyCost = $buysUpToDate->sum(fn ($t) => (float) $t->shares * (float) $t->price_per_share + (float) $t->handling_fee);
                    $avgCost = $totalBuyShares > 0 ? $totalBuyCost / $totalBuyShares : 0;
                    $totalCostBasis += $avgCost * $netShares;
                }
            }

            if ($totalValue > 0) {
                $result[] = [
                    'date'       => $date,
                    'value'      => round($totalValue, 2),
                    'cost_basis' => round($totalCostBasis, 2),
                ];
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
