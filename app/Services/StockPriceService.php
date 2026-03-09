<?php

namespace App\Services;

use App\Models\Stock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockPriceService
{
    public function __construct(private readonly string $apiKey) {}

    public function updatePrice(Stock $stock): void
    {
        $response = Http::get('https://www.alphavantage.co/query', [
            'function' => 'GLOBAL_QUOTE',
            'symbol' => $stock->symbol,
            'apikey' => $this->apiKey,
        ]);

        $quote = $response->json('Global Quote');

        if (empty($quote) || empty($quote['05. price'])) {
            Log::warning("StockPriceService: no price data returned for {$stock->symbol}");

            return;
        }

        $stock->update([
            'current_price' => (float) $quote['05. price'],
            'previous_close' => (float) $quote['08. previous close'],
            'change_percent' => (float) rtrim($quote['10. change percent'], '%'),
            'last_fetched_at' => now(),
        ]);
    }
}
