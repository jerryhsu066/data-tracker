<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use App\Models\StockSplit;
use App\Models\StockTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $discount = (float) ($request->user()->handling_fee_discount ?? 0);

        $positions = Stock::with([
                'transactions' => fn ($q) => $q->where('user_id', $userId),
                'splits',
            ])
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

        $transactions = StockTransaction::where('user_id', $userId)
            ->with('stock')
            ->orderBy('transacted_at')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([]);
        }

        $stockIds = $transactions->pluck('stock_id')->unique()->values();

        $histories = StockPriceHistory::whereIn('stock_id', $stockIds)
            ->orderBy('date')
            ->get()
            ->groupBy('stock_id');

        if ($histories->isEmpty()) {
            return response()->json([]);
        }

        // Load all splits grouped by stock_id
        $splitsByStock = StockSplit::whereIn('stock_id', $stockIds)
            ->orderBy('split_date')
            ->get()
            ->groupBy('stock_id');

        $today = Carbon::today('Asia/Taipei')->toDateString();

        $allDates = $histories->flatten()
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->filter(fn ($d) => $d <= $today)
            ->unique()->sort()->values();

        $priceIndex = [];
        foreach ($stockIds as $stockId) {
            $priceIndex[$stockId] = ($histories[$stockId] ?? collect())
                ->filter(fn ($h) => (float) $h->close_price > 0)
                ->map(fn ($h) => ['date' => (string) $h->date, 'price' => (float) $h->close_price])
                ->values()
                ->all();
        }

        $txByStock = $transactions->groupBy('stock_id');

        $result = [];
        $cursors = array_fill_keys($stockIds->all(), 0);

        foreach ($allDates as $date) {
            $totalValue = 0.0;
            $totalCostBasis = 0.0;

            foreach ($stockIds as $stockId) {
                $splits = $splitsByStock[$stockId] ?? collect();

                $txUpToDate = ($txByStock[$stockId] ?? collect())
                    ->filter(fn ($t) => (string) $t->transacted_at <= $date);

                // Apply date-scoped split multiplier per transaction
                $netShares       = 0.0;
                $totalBuyShares  = 0.0;
                $totalBuyCost    = 0.0;

                foreach ($txUpToDate as $tx) {
                    $multiplier = Stock::splitMultiplierBetween(
                        (string) $tx->transacted_at,
                        $date,
                        $splits
                    );
                    $adjusted = (float) $tx->shares * $multiplier;

                    if ($tx->type === 'buy') {
                        $netShares      += $adjusted;
                        $totalBuyShares += $adjusted;
                        $totalBuyCost   += (float) $tx->shares * (float) $tx->price_per_share + (float) $tx->handling_fee;
                    } else {
                        $netShares -= $adjusted;
                    }
                }

                if ($netShares <= 0) {
                    continue;
                }

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
        $splits       = $stock->splits;

        $netShares      = 0.0;
        $totalBuyShares = 0.0;
        $totalBuyCost   = 0.0;

        $totalSellShares  = 0.0;
        $totalSellRevenue = 0.0;

        foreach ($transactions as $tx) {
            $multiplier = Stock::splitMultiplierSince((string) $tx->transacted_at, $splits);
            $adjusted   = (float) $tx->shares * $multiplier;

            if ($tx->type === 'buy') {
                $netShares      += $adjusted;
                $totalBuyShares += $adjusted;
                $totalBuyCost   += (float) $tx->shares * (float) $tx->price_per_share + (float) $tx->handling_fee;
            } else {
                $netShares       -= $adjusted;
                $totalSellShares += $adjusted;
                $totalSellRevenue += (float) $tx->shares * (float) $tx->price_per_share - (float) $tx->handling_fee - (float) $tx->transaction_tax;
            }
        }

        $averageCost = $totalBuyShares > 0 ? $totalBuyCost / $totalBuyShares : 0;

        $currentPrice = (float) ($stock->current_price ?? 0);
        $currentValue = $netShares * $currentPrice;

        $unrealizedGain = 0.0;
        if ($netShares > 0 && $currentPrice > 0) {
            $minFee = $netShares >= 1000 && fmod($netShares, 1000) == 0 ? 20 : 1;
            $sellHandlingFee = (int) max($minFee, floor($currentValue * 0.001425 * (1 - $discount)));
            $sellTax = (int) floor($currentValue * 0.003);
            $unrealizedGain = $currentValue - ($averageCost * $netShares) - $sellHandlingFee - $sellTax;
        }

        $realizedGain = $totalSellRevenue - ($totalSellShares * $averageCost);

        return [
            'stock'          => $stock->only(['id', 'symbol', 'name', 'current_price', 'change_percent', 'last_fetched_at']),
            'net_shares'     => number_format($netShares, 4, '.', ''),
            'average_cost'   => number_format($averageCost, 4, '.', ''),
            'current_value'  => number_format($currentValue, 4, '.', ''),
            'unrealized_gain' => number_format($unrealizedGain, 4, '.', ''),
            'realized_gain'  => number_format($realizedGain, 4, '.', ''),
        ];
    }
}
