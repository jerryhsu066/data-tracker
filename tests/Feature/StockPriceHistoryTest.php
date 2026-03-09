<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\StockPriceHistory;
use App\Services\StockPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_fetch_records_daily_price_history(): void
    {
        Http::fake([
            '*alphavantage*' => Http::response([
                'Global Quote' => [
                    '05. price' => '875.0000',
                    '08. previous close' => '860.0000',
                    '10. change percent' => '1.7442%',
                ],
            ]),
        ]);

        $stock = Stock::factory()->create(['symbol' => '2330.TW']);

        app(StockPriceService::class)->updatePrice($stock);

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_id' => $stock->id,
            'close_price' => 875.0000,
        ]);
    }

    public function test_repeated_fetch_on_same_day_upserts_price(): void
    {
        Http::fake([
            '*alphavantage*' => Http::sequence()
                ->push(['Global Quote' => ['05. price' => '870.0000', '08. previous close' => '860.0000', '10. change percent' => '1.1628%']])
                ->push(['Global Quote' => ['05. price' => '875.0000', '08. previous close' => '860.0000', '10. change percent' => '1.7442%']]),
        ]);

        $stock = Stock::factory()->create(['symbol' => '2330.TW']);
        $service = app(StockPriceService::class);

        $service->updatePrice($stock);
        $service->updatePrice($stock);

        // Only one record per day per stock
        $this->assertDatabaseCount('stock_price_histories', 1);
        $this->assertDatabaseHas('stock_price_histories', ['close_price' => 875.0000]);
    }

    public function test_can_get_price_history_for_a_stock(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);
        StockPriceHistory::factory()->create(['stock_id' => $stock->id, 'date' => '2025-01-01', 'close_price' => 800]);
        StockPriceHistory::factory()->create(['stock_id' => $stock->id, 'date' => '2025-01-02', 'close_price' => 820]);
        StockPriceHistory::factory()->create(['stock_id' => $stock->id, 'date' => '2025-01-03', 'close_price' => 850]);

        $response = $this->getJson('/api/stocks/2330.TW/prices');

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment(['close_price' => '800.0000', 'date' => '2025-01-01'])
            ->assertJsonFragment(['close_price' => '850.0000', 'date' => '2025-01-03']);
    }
}
