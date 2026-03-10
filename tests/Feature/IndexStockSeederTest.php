<?php

namespace Tests\Feature;

use App\Jobs\FetchHistoricalPrices;
use App\Models\Stock;
use Database\Seeders\IndexStockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndexStockSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_index_stocks(): void
    {
        Queue::fake();

        $this->seed(IndexStockSeeder::class);

        $this->assertDatabaseHas('stocks', ['symbol' => '^TWII']);
        $this->assertDatabaseHas('stocks', ['symbol' => '^IXIC']);
        $this->assertDatabaseHas('stocks', ['symbol' => '^VIX']);
        $this->assertEquals(3, Stock::count());
    }

    public function test_seeder_dispatches_history_fetch_for_each_new_stock(): void
    {
        Queue::fake();

        $this->seed(IndexStockSeeder::class);

        Queue::assertPushed(FetchHistoricalPrices::class, 3);
        Queue::assertPushed(FetchHistoricalPrices::class, fn ($job) =>
            $job->stock->symbol === '^TWII'
        );
        Queue::assertPushed(FetchHistoricalPrices::class, fn ($job) =>
            $job->stock->symbol === '^IXIC'
        );
        Queue::assertPushed(FetchHistoricalPrices::class, fn ($job) =>
            $job->stock->symbol === '^VIX'
        );
    }

    public function test_history_fetch_covers_one_month(): void
    {
        Queue::fake();

        $this->seed(IndexStockSeeder::class);

        $expectedFrom = now()->subMonth()->toDateString();

        Queue::assertPushed(FetchHistoricalPrices::class, fn ($job) =>
            $job->fromDate === $expectedFrom
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        Queue::fake();

        $this->seed(IndexStockSeeder::class);
        $this->seed(IndexStockSeeder::class);

        $this->assertEquals(3, Stock::count());
    }

    public function test_seeder_does_not_redispatch_for_existing_stocks(): void
    {
        Queue::fake();

        Stock::factory()->create(['symbol' => '^TWII', 'name' => 'Taiwan Weighted Index']);
        Stock::factory()->create(['symbol' => '^IXIC', 'name' => 'NASDAQ Composite']);
        Stock::factory()->create(['symbol' => '^VIX',  'name' => 'CBOE Volatility Index']);

        $this->seed(IndexStockSeeder::class);

        Queue::assertNothingPushed();
    }
}
