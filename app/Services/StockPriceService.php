<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockPriceService
{
    private function chartRequest(string $symbol, array $params): \Illuminate\Http\Client\Response
    {
        $url = fn ($s) => 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($s);
        $headers = ['User-Agent' => 'Mozilla/5.0'];

        $response = Http::withHeaders($headers)->get($url($symbol), $params);

        // Taiwan OTC-market symbols (e.g. bond ETFs) use .TWO on Yahoo Finance instead of .TW
        if ($response->status() === 404
            && str_ends_with($symbol, '.TW')
            && ! str_ends_with($symbol, '.TWO')
        ) {
            $response = Http::withHeaders($headers)->get($url(substr($symbol, 0, -3) . '.TWO'), $params);
        }

        return $response;
    }

    public function updatePrice(Stock $stock): void
    {
        $response = $this->chartRequest($stock->symbol, [
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

        // Only record daily history when the price data is actually from today
        // (market open or closed). If regularMarketTime is from a previous day
        // the market hasn't opened yet — skip saving to avoid stale/duplicate entries.
        $tz = 'Asia/Taipei';
        $today = now()->timezone($tz)->toDateString();
        $priceDate = isset($meta['regularMarketTime'])
            ? Carbon::createFromTimestamp($meta['regularMarketTime'])->timezone($tz)->toDateString()
            : $today;

        if ($price > 0 && $priceDate === $today) {
            StockPriceHistory::updateOrCreate(
                ['stock_id' => $stock->id, 'date' => $today],
                ['close_price' => $price],
            );
        }
    }

    public function fetchHistoricalPrices(Stock $stock, string $fromDate): void
    {
        $tz = 'Asia/Taipei';
        $period1 = Carbon::parse($fromDate, $tz)->startOfDay()->timestamp;
        // Stop at yesterday — today's data is incomplete until market closes (13:30 Taiwan time)
        $period2 = Carbon::yesterday($tz)->endOfDay()->timestamp;

        $response = $this->chartRequest($stock->symbol, [
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
            if ($close === null || $close <= 0) {
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
