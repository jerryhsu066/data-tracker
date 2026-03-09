<?php

namespace Tests\Feature;

use App\Jobs\FetchStockPrice;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_tracked_stocks(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'current_price' => 150.00]);
        Stock::factory()->create(['symbol' => 'TSLA', 'name' => 'Tesla Inc.', 'current_price' => 200.00]);

        $response = $this->getJson('/api/stocks');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['symbol' => 'AAPL'])
            ->assertJsonFragment(['symbol' => 'TSLA']);
    }

    public function test_can_add_a_stock_to_track(): void
    {
        $response = $this->postJson('/api/stocks', [
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['symbol' => 'AAPL']);

        $this->assertDatabaseHas('stocks', ['symbol' => 'AAPL']);
    }

    public function test_symbol_is_stored_uppercase(): void
    {
        $response = $this->postJson('/api/stocks', [
            'symbol' => 'aapl',
            'name' => 'Apple Inc.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('stocks', ['symbol' => 'AAPL']);
    }

    public function test_cannot_add_duplicate_stock_symbol(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL']);

        $response = $this->postJson('/api/stocks', [
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol']);
    }

    public function test_symbol_and_name_are_required(): void
    {
        $response = $this->postJson('/api/stocks', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol', 'name']);
    }

    public function test_can_show_a_stock_by_symbol(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'current_price' => 150.00]);

        $response = $this->getJson('/api/stocks/AAPL');

        $response->assertOk()
            ->assertJsonFragment(['symbol' => 'AAPL', 'name' => 'Apple Inc.']);
    }

    public function test_show_returns_404_for_unknown_symbol(): void
    {
        $this->getJson('/api/stocks/FAKE')->assertNotFound();
    }

    public function test_can_delete_a_tracked_stock(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL']);

        $this->deleteJson('/api/stocks/AAPL')->assertNoContent();

        $this->assertDatabaseMissing('stocks', ['symbol' => 'AAPL']);
    }

    public function test_fetch_endpoint_dispatches_fetch_stock_price_job(): void
    {
        Queue::fake();
        Stock::factory()->create(['symbol' => 'AAPL']);

        $this->postJson('/api/stocks/AAPL/fetch')->assertAccepted();

        Queue::assertPushed(FetchStockPrice::class, fn ($job) => $job->stock->symbol === 'AAPL');
    }
}
