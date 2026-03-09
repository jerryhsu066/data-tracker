<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use Carbon\Carbon;
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

    public function fetchHistoricalPrices(Stock $stock, string $fromDate): void
    {
        $tz = 'Asia/Taipei';
        $period1 = Carbon::parse($fromDate, $tz)->startOfDay()->timestamp;
        $period2 = Carbon::now($tz)->endOfDay()->timestamp;

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->get('https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($stock->symbol), [
            'interval' => '1d',
            'period1'  => $period1,
            'period2'  => $period2,
        ]);

        $result = $response->json('chart.result.0');

        if (empty($result)) {
            Log::warning("StockPriceService: no historical data for {$stock->symbol} from {$fromDate}");
            return;
        }

        $timestamps = $result['timestamp'] ?? [];
        $closes = $result['indicators']['quote'][0]['close'] ?? [];

        foreach ($timestamps as $i => $timestamp) {
            $close = $closes[$i] ?? null;
            if ($close === null) {
                continue;
            }

            $date = Carbon::createFromTimestamp($timestamp)->timezone($tz)->toDateString();

            StockPriceHistory::updateOrCreate(
                ['stock_id' => $stock->id, 'date' => $date],
                ['close_price' => round($close, 4)],
            );
        }
    }
}
