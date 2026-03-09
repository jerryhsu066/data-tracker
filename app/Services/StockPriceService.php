<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockPriceService
{
    public function updatePrice(Stock $stock): void
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->get('https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($stock->symbol), [
            'interval' => '1d',
            'range'    => '1d',
        ]);

        $meta = $response->json('chart.result.0.meta');

        if (empty($meta) || ! isset($meta['regularMarketPrice'])) {
            Log::warning("StockPriceService: no price data returned for {$stock->symbol}");

            return;
        }

        $price = (float) $meta['regularMarketPrice'];
        $previousClose = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
        $changePercent = $previousClose > 0
            ? round(($price - $previousClose) / $previousClose * 100, 4)
            : 0;

        $stock->update([
            'current_price'  => $price,
            'previous_close' => $previousClose,
            'change_percent' => $changePercent,
            'last_fetched_at' => now(),
        ]);

        $today = now()->timezone('Asia/Taipei')->toDateString();

        StockPriceHistory::updateOrCreate(
            ['stock_id' => $stock->id, 'date' => $today],
            ['close_price' => $price],
        );
    }
}
