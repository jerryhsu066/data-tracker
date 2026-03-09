<?php

namespace Tests\Unit;

use App\Jobs\FetchAllStockPrices;
use App\Jobs\FetchStockPrice;
use App\Models\Stock;
use App\Services\StockPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchStockPriceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_stock_price_job_updates_stock_price(): void
    {
        Http::fake([
            '*alphavantage*' => Http::response([
                'Global Quote' => [
                    '05. price' => '175.5000',
                    '08. previous close' => '172.0000',
                    '10. change percent' => '2.0349%',
                ],
            ]),
        ]);

        $stock = Stock::factory()->create(['symbol' => 'AAPL']);

        $service = app(StockPriceService::class);
        $service->updatePrice($stock);

        $stock->refresh();

        $this->assertEquals('175.5000', $stock->current_price);
        $this->assertEquals('172.0000', $stock->previous_close);
        $this->assertEquals('2.0349', $stock->change_percent);
        $this->assertNotNull($stock->last_fetched_at);
    }

    public function test_fetch_stock_price_job_handles_api_error_gracefully(): void
    {
        Http::fake([
            '*alphavantage*' => Http::response(['Note' => 'API call limit reached'], 200),
        ]);

        $stock = Stock::factory()->create(['symbol' => 'AAPL', 'current_price' => 150.0000]);

        $service = app(StockPriceService::class);
        $service->updatePrice($stock);

        $stock->refresh();

        // Price should remain unchanged on bad response
        $this->assertEquals('150.0000', $stock->current_price);
        $this->assertNull($stock->last_fetched_at);
    }

    public function test_fetch_all_stocks_job_dispatches_fetch_for_each_stock(): void
    {
        Queue::fake();

        Stock::factory()->create(['symbol' => 'AAPL']);
        Stock::factory()->create(['symbol' => 'TSLA']);
        Stock::factory()->create(['symbol' => 'GOOG']);

        (new FetchAllStockPrices)->handle();

        Queue::assertPushed(FetchStockPrice::class, 3);
    }
}
